<?php

namespace App\Modules\Stock\Import;

use App\Modules\Access\Models\User;
use App\Modules\Imports\ImportPlan;
use App\Modules\Imports\ImportRecorder;
use App\Modules\Imports\SpreadsheetReader as Sheet;
use App\Modules\Imports\TabularImport;
use App\Modules\Stock\Models\RecipeItem;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockMovement;
use App\Modules\Stock\Models\Unit;
use App\Modules\Stock\StockLedger;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Collection;

/**
 * Insumos do estoque com unidade, mínimo e (opcional) saldo e custo. O saldo entra pelo livro do
 * estoque: insumo novo vira uma compra (com o custo); insumo existente, uma contagem.
 */
class StockItemsImport implements TabularImport
{
    public const REASON = 'Importação de planilha';

    /** Como as pessoas escrevem a unidade => código. */
    public const UNIT_ALIASES = [
        'un' => 'un', 'und' => 'un', 'unid' => 'un', 'unidade' => 'un', 'unidades' => 'un', 'pc' => 'un', 'peca' => 'un', 'pct' => 'un',
        'kg' => 'kg', 'quilo' => 'kg', 'quilos' => 'kg', 'kilo' => 'kg', 'quilograma' => 'kg',
        'g' => 'g', 'gr' => 'g', 'grama' => 'g', 'gramas' => 'g',
        'l' => 'l', 'lt' => 'l', 'litro' => 'l', 'litros' => 'l',
        'ml' => 'ml', 'mililitro' => 'ml', 'mililitros' => 'ml',
    ];

    public function __construct(private readonly StockLedger $ledger) {}

    public function kind(): string
    {
        return 'stock_items';
    }

    public function title(): string
    {
        return 'Insumos do estoque';
    }

    public function noun(): array
    {
        return ['insumo', 'insumos', 'm'];
    }

    public function permission(): string
    {
        return 'stock.manage';
    }

    public function columns(): array
    {
        return [
            'name' => ['Insumo', ['insumo', 'nome', 'item', 'materia prima', 'ingrediente']],
            'unit' => ['Unidade', ['unidade', 'un', 'medida', 'unidade de medida']],
            'min' => ['Estoque mínimo', ['estoque minimo', 'minimo', 'min']],
            'quantity' => ['Quantidade atual', ['quantidade atual', 'quantidade', 'estoque', 'estoque atual', 'saldo']],
            'cost' => ['Custo por unidade', ['custo por unidade', 'custo', 'custo unitario', 'preco de custo', 'valor unitario']],
        ];
    }

    public function required(): array
    {
        return ['name', 'unit'];
    }

    public function widths(): array
    {
        return [30, 11, 16, 17, 18];
    }

    public function examples(): array
    {
        return [
            ['Muçarela', 'kg', '2', '5,5', '38,90'],
            ['Farinha de trigo', 'kg', '10', '25', '4,20'],
            ['Refrigerante lata', 'un', '24', '48', '2,80'],
            ['Óleo de soja', 'l', '5', '', ''],
        ];
    }

    public function instructions(): array
    {
        return [
            '# Como preencher a planilha de insumos',
            '',
            'Insumo e Unidade são obrigatórios. Unidade: un, kg, g, l ou ml (também vale "quilo", "litro", "unidade"...).',
            'Estoque mínimo: abaixo dele o insumo aparece como "estoque baixo" no Início.',
            'Quantidade atual e Custo por unidade (R$ por kg, por litro, por unidade) são opcionais.',
            'Insumo novo com quantidade entra como uma compra (com o custo); insumo que já existe tem o saldo ajustado como uma contagem.',
            'O insumo é reconhecido pelo nome: importar de novo atualiza mínimo, saldo e custo.',
        ];
    }

    public function current(): iterable
    {
        foreach (StockItem::with('unit')->orderBy('name')->get() as $item) {
            yield [
                $item->name, $item->unit->code, Quantity::format((string) $item->min_quantity), Quantity::format((string) $item->quantity),
                number_format($item->average_cost_cents / 100, 2, ',', '.'),
            ];
        }
    }

    public function plan(array $rows, array $columns, string $mode): ImportPlan
    {
        $plan = new ImportPlan;
        $units = Unit::all()->keyBy('code');
        $existing = StockItem::with('unit')->get()->keyBy(fn ($i) => Sheet::plain($i->name));
        $used = RecipeItem::query()->distinct()->pluck('stock_item_id')
            ->merge(StockMovement::query()->distinct()->pluck('stock_item_id'))->flip();
        $seen = [];

        foreach ($rows as ['line' => $line, 'values' => $v]) {
            $name = Sheet::text($v['name'] ?? null);
            $unitText = Sheet::text($v['unit'] ?? null);
            $unit = $units->get(self::UNIT_ALIASES[Sheet::plain($unitText)] ?? '');
            $min = Sheet::blank($v['min'] ?? null) ? null : (Quantity::isValid($v['min']) ? Quantity::parse($v['min']) : false);
            $quantity = Sheet::blank($v['quantity'] ?? null) ? null : (Quantity::isValid($v['quantity']) ? Quantity::parse($v['quantity']) : false);
            $cost = Sheet::blank($v['cost'] ?? null) ? null : Sheet::money($v['cost']);
            $key = Sheet::plain($name);
            $item = $existing->get($key);

            $errors = array_keys(array_filter([
                'falta o Insumo' => $name === '',
                'nome com mais de 120 caracteres' => mb_strlen($name) > 120,
                'falta a Unidade' => $unitText === '',
                "unidade \"{$unitText}\" não reconhecida (use un, kg, g, l ou ml)" => $unitText !== '' && ! $unit,
                'estoque mínimo inválido' => $min === false || ($min !== null && Quantity::compare($min, '0') < 0),
                'quantidade inválida' => $quantity === false || ($quantity !== null && Quantity::compare($quantity, '0') < 0),
                'custo inválido ("'.Sheet::text($v['cost'] ?? null).'"), use 38,90' => ! Sheet::blank($v['cost'] ?? null) && $cost === null,
                'insumo repetido na linha '.($seen[$key] ?? '') => $key !== '' && isset($seen[$key]),
                // Trocar a unidade de quem já tem movimento ou ficha bagunçaria saldo e fichas.
                'a unidade não pode mudar: o insumo já tem movimentação ou ficha técnica' => $item && $unit && $item->unit_id !== $unit->id && isset($used[$item->id]),
            ]));

            if ($errors) {
                $plan->errors[] = "Linha {$line}: ".implode('; ', $errors).'.';

                continue;
            }

            $seen[$key] = $line;
            $data = ['name' => $name, 'unit_id' => $unit->id, 'min' => $min, 'quantity' => $quantity, 'cost' => $cost];
            $detail = collect([
                $quantity !== null ? Quantity::format($quantity).' '.$unit->code : null,
                $min !== null ? 'mínimo '.Quantity::format($min) : null,
                $cost !== null ? Money::cents($cost)->format().'/'.$unit->code : null,
            ])->filter()->implode(' · ') ?: $unit->code;

            if (! $item) {
                $plan->add($line, 'create', $name, $detail, [], $data);

                continue;
            }

            $changes = array_values(array_filter([
                $item->unit_id !== $unit->id ? "unidade: {$item->unit->code} → {$unit->code}" : null,
                $min !== null && Quantity::compare((string) $item->min_quantity, $min) !== 0 ? 'mínimo: '.Quantity::format((string) $item->min_quantity).' → '.Quantity::format($min) : null,
                $quantity !== null && Quantity::compare((string) $item->quantity, $quantity) !== 0 ? 'saldo: '.Quantity::format((string) $item->quantity).' → '.Quantity::format($quantity).' '.$unit->code : null,
                $cost !== null && $item->average_cost_cents !== $cost ? 'custo: '.Money::cents($item->average_cost_cents)->format().' → '.Money::cents($cost)->format() : null,
            ]));

            $plan->add($line, $mode === 'skip' ? 'skip' : ($changes ? 'update' : 'same'), $item->name, $detail, $changes, $data + ['id' => $item->id]);
        }

        return $plan;
    }

    public function apply(ImportPlan $plan, ImportRecorder $recorder, ?User $user): void
    {
        foreach ($plan->rows as ['action' => $action, 'data' => $d]) {
            if ($action === 'create') {
                $item = StockItem::create(['name' => $d['name'], 'unit_id' => $d['unit_id'], 'min_quantity' => $d['min'] ?? '0']);
                $recorder->track('stock_item', $item->id, 'created');

                if ($d['quantity'] !== null && Quantity::isPositive($d['quantity'])) {
                    $total = (int) Quantity::round(Quantity::mul((string) ($d['cost'] ?? 0), $d['quantity']));
                    $this->ledger->purchase($item, $d['quantity'], Money::cents($total), $user, self::REASON);
                }
            } elseif ($action === 'update') {
                $item = StockItem::findOrFail($d['id']);
                $recorder->track('stock_item', $item->id, 'updated', [
                    'unit_id' => $item->unit_id, 'min_quantity' => (string) $item->min_quantity,
                    'quantity' => (string) $item->quantity, 'average_cost_cents' => $item->average_cost_cents,
                ]);
                $item->fill(array_filter(['unit_id' => $d['unit_id'], 'min_quantity' => $d['min']], fn ($v) => $v !== null))->save();

                if ($d['quantity'] !== null && Quantity::compare((string) $item->quantity, $d['quantity']) !== 0) {
                    $this->ledger->count($item->fresh(), $d['quantity'], $user, self::REASON);
                }

                if ($d['cost'] !== null) {
                    $item->forceFill(['average_cost_cents' => $d['cost']])->save();
                }
            }
        }
    }

    public function undo(Collection $items, ?User $user): void
    {
        foreach ($items->where('subject_type', 'stock_item') as $entry) {
            $item = StockItem::find($entry->subject_id);

            if (! $item) {
                continue;
            }

            if ($entry->action === 'created') {
                // Já usado numa ficha técnica depois: só desativa.
                RecipeItem::where('stock_item_id', $item->id)->exists() ? $item->update(['is_active' => false]) : $item->delete();

                continue;
            }

            $before = $entry->before;
            $item->fill(['unit_id' => $before['unit_id'], 'min_quantity' => $before['min_quantity']])->save();

            if (Quantity::compare((string) $item->quantity, $before['quantity']) !== 0) {
                $this->ledger->count($item->fresh(), $before['quantity'], $user, 'Importação desfeita');
            }

            $item->forceFill(['average_cost_cents' => $before['average_cost_cents']])->save();
        }
    }
}
