<?php

namespace App\Modules\Stock\Actions;

use App\Modules\Access\Audit;
use App\Modules\Menu\Models\Addon;
use App\Modules\Menu\Models\Product;
use App\Modules\Stock\Models\Recipe;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\Unit;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveRecipe
{
    /**
     * Substitui a ficha técnica de um produto ou adicional. Lista vazia remove a ficha.
     *
     * @param  list<array{stock_item_id: int, quantity: string, unit_id: int}>  $lines
     */
    public function handle(Product|Addon $owner, array $lines): ?Recipe
    {
        $key = $owner instanceof Product ? 'product_id' : 'addon_id';
        $items = StockItem::with('unit')->whereKey(array_column($lines, 'stock_item_id'))->get()->keyBy('id');
        $units = Unit::all()->keyBy('id');
        $seen = [];

        foreach (array_values($lines) as $i => $line) {
            $item = $items->get((int) $line['stock_item_id']);
            $unit = $units->get((int) $line['unit_id']);

            if (! $item || ! $unit) {
                throw ValidationException::withMessages(["lines.{$i}" => 'Escolha o insumo e a unidade.']);
            }

            if (isset($seen[$item->id])) {
                throw ValidationException::withMessages(["lines.{$i}" => "{$item->name} aparece duas vezes."]);
            }

            if (! $unit->compatibleWith($item->unit)) {
                throw ValidationException::withMessages(["lines.{$i}" => "{$item->name} é controlado em {$item->unit->code}; use uma unidade compatível."]);
            }

            if (! Quantity::isPositive($line['quantity'])) {
                throw ValidationException::withMessages(["lines.{$i}" => 'Quantidade deve ser maior que zero.']);
            }

            $seen[$item->id] = true;
        }

        return DB::connection('tenant')->transaction(function () use ($owner, $key, $lines, $items, $units) {
            $recipe = Recipe::firstWhere($key, $owner->getKey());

            if ($lines === []) {
                $recipe?->delete();
                Audit::record('recipe.updated', $owner, ['ficha' => 'removida']);

                return null;
            }

            $recipe ??= Recipe::create([$key => $owner->getKey()]);
            $recipe->items()->delete();

            foreach ($lines as $line) {
                $recipe->items()->create([
                    'stock_item_id' => (int) $line['stock_item_id'],
                    'quantity' => $line['quantity'],
                    'unit_id' => (int) $line['unit_id'],
                ]);
            }

            Audit::record('recipe.updated', $owner, [
                'ficha' => collect($lines)->map(fn ($l) => Quantity::format($l['quantity']).' '.$units[$l['unit_id']]->code.' '.$items[$l['stock_item_id']]->name)->implode(', '),
            ]);

            return $recipe->load('items.stockItem.unit', 'items.unit');
        });
    }
}
