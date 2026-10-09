<?php

namespace App\Livewire\Tenant\Stock;

use App\Modules\Menu\Models\Addon;
use App\Modules\Menu\Models\Product;
use App\Modules\Stock\Actions\SaveRecipe;
use App\Modules\Stock\Models\Recipe;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\Unit;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Ficha técnica de um produto (/estoque/fichas/produto/{id}) ou adicional (/estoque/fichas/adicional/{id}). */
class RecipeEditor extends Component
{
    #[Locked]
    public string $ownerType = 'product';

    #[Locked]
    public int $ownerId;

    /** @var list<array{stock_item_id: int|string|null, quantity: string, unit_id: int|string|null}> */
    public array $lines = [];

    public function mount(?Product $product = null, ?Addon $addon = null): void
    {
        $owner = $product?->exists ? $product : $addon;
        abort_unless($owner?->exists, 404);

        $this->ownerType = $owner instanceof Product ? 'product' : 'addon';
        $this->ownerId = $owner->getKey();

        $recipe = Recipe::with('items')->firstWhere($this->ownerType.'_id', $this->ownerId);

        $this->lines = $recipe?->items->map(fn ($i) => [
            'stock_item_id' => $i->stock_item_id,
            'quantity' => Quantity::format($i->quantity),
            'unit_id' => $i->unit_id,
        ])->all() ?? [];

        if ($this->lines === []) {
            $this->addLine();
        }
    }

    public function addLine(): void
    {
        $this->lines[] = ['stock_item_id' => null, 'quantity' => '', 'unit_id' => null];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    /** Ao escolher o insumo, a unidade da linha vira a dele (pode trocar por uma compatível). */
    public function updatedLines($value, ?string $key = null): void
    {
        if (! $key || ! str_contains($key, '.')) {
            return;
        }

        [$index, $field] = explode('.', $key);

        if ($field === 'stock_item_id' && $value) {
            $this->lines[$index]['unit_id'] = StockItem::find($value)?->unit_id;
        }
    }

    private function owner(): Product|Addon
    {
        return $this->ownerType === 'product' ? Product::withTrashed()->findOrFail($this->ownerId) : Addon::findOrFail($this->ownerId);
    }

    public function save(SaveRecipe $save): void
    {
        $this->authorize('stock.manage');
        $lines = [];

        foreach ($this->lines as $i => $line) {
            if (! $line['stock_item_id'] && trim($line['quantity']) === '') {
                continue; // linha em branco
            }

            if (! Quantity::isValid($line['quantity'])) {
                $this->addError("lines.{$i}", 'Quantidade inválida. Ex.: 0,2 ou 150');

                return;
            }

            $lines[] = ['stock_item_id' => (int) $line['stock_item_id'], 'quantity' => Quantity::parse($line['quantity']), 'unit_id' => (int) $line['unit_id']];
        }

        try {
            $save->handle($this->owner(), $lines);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }

            return;
        }

        session()->flash('status', 'Ficha técnica salva.');
        $this->redirectRoute('stock.recipes', navigate: true);
    }

    public function render()
    {
        $owner = $this->owner();
        $items = StockItem::with('unit')->where('is_active', true)->orderBy('name')->get();
        $units = Unit::all();

        // Custo estimado das linhas válidas, para mostrar antes de salvar.
        $cost = '0';
        foreach ($this->lines as $line) {
            $item = $items->firstWhere('id', (int) $line['stock_item_id']);
            $unit = $units->firstWhere('id', (int) $line['unit_id']);

            if ($item && $unit && $unit->compatibleWith($item->unit) && Quantity::isValid($line['quantity'])) {
                $cost = Quantity::add($cost, Quantity::mul($unit->convert(Quantity::parse($line['quantity']), $item->unit), (string) $item->average_cost_cents, 6));
            }
        }

        $cost = Money::cents((int) Quantity::round($cost));
        $price = $owner->price_cents;

        return view('livewire.tenant.stock.recipe-editor', [
            'owner' => $owner,
            'items' => $items,
            'units' => $units,
            'cost' => $cost,
            'price' => $price,
            'margin' => $price->cents > 0 ? round(($price->cents - $cost->cents) / $price->cents * 100, 1) : null,
        ])->title('Ficha · '.$owner->name);
    }
}
