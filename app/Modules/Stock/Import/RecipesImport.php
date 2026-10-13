<?php

namespace App\Modules\Stock\Import;

use App\Modules\Access\Models\User;
use App\Modules\Imports\ImportPlan;
use App\Modules\Imports\ImportRecorder;
use App\Modules\Imports\SpreadsheetReader as Sheet;
use App\Modules\Imports\TabularImport;
use App\Modules\Menu\Models\Addon;
use App\Modules\Menu\Models\Product;
use App\Modules\Stock\Models\Recipe;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\Unit;
use App\Support\Quantity;
use Illuminate\Support\Collection;

/**
 * Fichas técnicas: uma linha por insumo de cada produto (ou adicional). As linhas do mesmo produto
 * formam a ficha inteira dele, que substitui a atual.
 */
class RecipesImport implements TabularImport
{
    public function kind(): string
    {
        return 'recipes';
    }

    public function title(): string
    {
        return 'Fichas técnicas';
    }

    public function noun(): array
    {
        return ['ficha', 'fichas', 'f'];
    }

    public function permission(): string
    {
        return 'stock.manage';
    }

    public function columns(): array
    {
        return [
            'product' => ['Produto', ['produto', 'prato', 'item do cardapio', 'nome do produto']],
            'code' => ['Código', ['codigo', 'cod', 'codigo do produto']],
            'addon' => ['Adicional', ['adicional', 'opcao', 'complemento']],
            'group' => ['Grupo', ['grupo', 'grupo de adicionais']],
            'item' => ['Insumo', ['insumo', 'ingrediente', 'materia prima']],
            'quantity' => ['Quantidade', ['quantidade', 'qtd', 'qtde', 'quant']],
            'unit' => ['Unidade', ['unidade', 'un', 'medida']],
        ];
    }

    public function required(): array
    {
        return ['item', 'quantity', 'unit'];
    }

    public function widths(): array
    {
        return [28, 10, 20, 22, 26, 12, 10];
    }

    public function examples(): array
    {
        return [
            ['Margherita', '101', '', '', 'Muçarela', '0,25', 'kg'],
            ['Margherita', '101', '', '', 'Farinha de trigo', '300', 'g'],
            ['Refrigerante lata', '301', '', '', 'Refrigerante lata', '1', 'un'],
            ['', '', 'Bacon', 'Adicionais da pizza', 'Bacon', '50', 'g'],
        ];
    }

    public function instructions(): array
    {
        return [
            '# Como preencher a planilha de fichas técnicas',
            '',
            'Uma linha por insumo. As linhas do mesmo produto formam a ficha dele, que substitui a ficha atual.',
            'Produto: o nome ou o Código do cardápio. Para a ficha de um adicional, deixe Produto em branco e preencha Adicional (e o Grupo, se o nome se repete).',
            'Insumo: o nome cadastrado no estoque (importe os insumos antes). Quantidade e Unidade de cada um (pode ser g para um insumo controlado em kg).',
            'Ao vender, o sistema baixa do estoque o que está na ficha.',
        ];
    }

    public function current(): iterable
    {
        foreach (Recipe::with(['items.stockItem', 'items.unit', 'product', 'addon.group'])->get() as $recipe) {
            foreach ($recipe->items as $line) {
                yield [
                    (string) $recipe->product?->name, (string) $recipe->product?->code,
                    (string) $recipe->addon?->name, (string) $recipe->addon?->group?->name,
                    $line->stockItem->name, Quantity::format((string) $line->quantity), $line->unit->code,
                ];
            }
        }
    }

    public function plan(array $rows, array $columns, string $mode): ImportPlan
    {
        $plan = new ImportPlan;
        $products = Product::query()->get(['id', 'name', 'code']);
        $byCode = $products->filter(fn ($p) => $p->code)->keyBy(fn ($p) => mb_strtolower($p->code));
        $byName = $products->groupBy(fn ($p) => Sheet::plain($p->name));
        $addons = Addon::with('group:id,name')->get(['id', 'name', 'addon_group_id'])->groupBy(fn ($a) => Sheet::plain($a->name));
        $items = StockItem::with('unit')->get()->keyBy(fn ($i) => Sheet::plain($i->name));
        $units = Unit::all()->keyBy('code');
        $recipes = Recipe::with('items')->get();

        /** @var array<string, array{owner: string, label: string, lines: list<array>, first: int}> $owners */
        $owners = [];

        foreach ($rows as ['line' => $line, 'values' => $v]) {
            $where = "Linha {$line}";
            $productText = Sheet::text($v['product'] ?? null);
            $code = Sheet::text($v['code'] ?? null);
            $addonText = Sheet::text($v['addon'] ?? null);
            $groupText = Sheet::text($v['group'] ?? null);
            $itemText = Sheet::text($v['item'] ?? null);
            $unitText = Sheet::text($v['unit'] ?? null);
            $errors = [];

            // De quem é a ficha: produto (código ou nome) ou adicional (nome, com o grupo se repetir).
            $owner = null;

            if ($code !== '' || $productText !== '') {
                $product = $code !== '' ? $byCode->get(mb_strtolower($code)) : null;
                $sameName = $byName->get(Sheet::plain($productText), collect());
                $product ??= $sameName->count() === 1 ? $sameName->first() : null;

                if ($product) {
                    $owner = ['key' => 'product:'.$product->id, 'type' => 'product_id', 'id' => $product->id, 'label' => $product->name];
                } else {
                    $errors[] = $sameName->count() > 1
                        ? "há mais de um produto \"{$productText}\": preencha o Código"
                        : 'produto "'.($code ?: $productText).'" não está no cardápio';
                }
            } elseif ($addonText !== '') {
                $candidates = $addons->get(Sheet::plain($addonText), collect())
                    ->filter(fn ($a) => $groupText === '' || Sheet::plain($a->group?->name) === Sheet::plain($groupText));

                if ($candidates->count() === 1) {
                    $addon = $candidates->first();
                    $owner = ['key' => 'addon:'.$addon->id, 'type' => 'addon_id', 'id' => $addon->id, 'label' => $addon->name.' ('.$addon->group?->name.')'];
                } else {
                    $errors[] = $candidates->isEmpty()
                        ? "adicional \"{$addonText}\" não encontrado".($groupText !== '' ? " no grupo {$groupText}" : '')
                        : "o adicional \"{$addonText}\" existe em mais de um grupo: preencha o Grupo";
                }
            } else {
                $errors[] = 'preencha o Produto (ou o Código) ou o Adicional';
            }

            $item = $items->get(Sheet::plain($itemText));
            $unit = $units->get(StockItemsImport::UNIT_ALIASES[Sheet::plain($unitText)] ?? '');
            $quantity = Quantity::isValid($v['quantity'] ?? null) ? Quantity::parse($v['quantity']) : null;

            $errors = array_merge($errors, array_keys(array_filter([
                'falta o Insumo' => $itemText === '',
                "o insumo \"{$itemText}\" não está no estoque (importe os insumos antes)" => $itemText !== '' && ! $item,
                'quantidade deve ser maior que zero' => $quantity === null || ! Quantity::isPositive($quantity),
                "unidade \"{$unitText}\" não reconhecida (use un, kg, g, l ou ml)" => ! $unit,
                'unidade incompatível com o insumo (controlado em '.($item?->unit?->code ?? '').')' => $item && $unit && ! $unit->compatibleWith($item->unit),
                "{$itemText} aparece duas vezes nesta ficha" => $owner && $item && collect($owners[$owner['key']]['lines'] ?? [])->contains('stock_item_id', $item->id),
            ])));

            if ($errors) {
                $plan->errors[] = "{$where}: ".implode('; ', $errors).'.';

                continue;
            }

            $owners[$owner['key']] ??= $owner + ['lines' => [], 'first' => $line];
            $owners[$owner['key']]['lines'][] = ['stock_item_id' => $item->id, 'quantity' => $quantity, 'unit_id' => $unit->id, 'text' => Quantity::format($quantity).' '.$unit->code.' '.$item->name];
        }

        foreach ($owners as $owner) {
            $current = $recipes->first(fn ($r) => $r->{$owner['type']} === $owner['id']);
            $detail = implode(', ', array_column($owner['lines'], 'text'));
            $data = ['type' => $owner['type'], 'id' => $owner['id'], 'lines' => array_map(fn ($l) => array_diff_key($l, ['text' => true]), $owner['lines'])];

            if (! $current) {
                $plan->add($owner['first'], 'create', $owner['label'], $detail, [], $data);

                continue;
            }

            $signature = fn ($lines) => collect($lines)->map(fn ($l) => $l['stock_item_id'].'|'.Quantity::parse((string) $l['quantity']).'|'.$l['unit_id'])->sort()->values()->all();
            $same = $signature($current->items->map->only(['stock_item_id', 'quantity', 'unit_id'])->all()) === $signature($data['lines']);
            $changes = $same ? [] : ['ficha: '.$current->items->count().' → '.count($data['lines']).' insumo(s)'];

            $plan->add($owner['first'], $mode === 'skip' ? 'skip' : ($same ? 'same' : 'update'), $owner['label'], $detail, $changes, $data + ['recipe_id' => $current->id]);
        }

        return $plan;
    }

    public function apply(ImportPlan $plan, ImportRecorder $recorder, ?User $user): void
    {
        foreach ($plan->rows as ['action' => $action, 'data' => $d]) {
            if (! in_array($action, ['create', 'update'], true)) {
                continue;
            }

            $recipe = Recipe::firstOrCreate([$d['type'] => $d['id']]);
            $recorder->track('recipe', $recipe->id, $action === 'create' ? 'created' : 'updated', $action === 'create' ? null : [
                'lines' => $recipe->items->map(fn ($l) => ['stock_item_id' => $l->stock_item_id, 'quantity' => (string) $l->quantity, 'unit_id' => $l->unit_id])->all(),
            ]);

            $recipe->items()->delete();
            $recipe->items()->createMany($d['lines']);
        }
    }

    public function undo(Collection $items, ?User $user): void
    {
        foreach ($items->where('subject_type', 'recipe') as $entry) {
            $recipe = Recipe::find($entry->subject_id);

            if (! $recipe) {
                continue;
            }

            if ($entry->action === 'created') {
                $recipe->delete();

                continue;
            }

            $recipe->items()->delete();
            $recipe->items()->createMany($entry->before['lines'] ?? []);
        }
    }
}
