<?php

namespace Database\Seeders\Tenant;

use App\Modules\Menu\Models\Addon;
use App\Modules\Menu\Models\Product;
use App\Modules\Stock\Actions\SaveRecipe;
use App\Modules\Stock\Models\Recipe;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\Unit;
use App\Modules\Stock\StockLedger;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Database\Seeder;

/**
 * Estoque de demonstração para o DemoMenuSeeder (pizzaria). Só para desenvolvimento:
 * php artisan tenants:seed --tenant=empresa1 --class="Database\Seeders\Tenant\DemoStockSeeder"
 */
class DemoStockSeeder extends Seeder
{
    /** nome => [unidade, quantidade comprada, valor total pago, mínimo] */
    private const ITEMS = [
        'Massa de pizza' => ['kg', '20', '160', '5'],
        'Muçarela' => ['kg', '10', '420', '3'],
        'Molho de tomate' => ['kg', '8', '96', '2'],
        'Calabresa' => ['kg', '5', '175', '1'],
        'Frango desfiado' => ['kg', '4', '120', '1'],
        'Catupiry' => ['kg', '3', '135', '1'],
        'Provolone' => ['kg', '2', '110', '0.5'],
        'Chocolate' => ['kg', '2', '70', '0.5'],
        'Banana' => ['kg', '3', '18', '1'],
        'Bacon' => ['kg', '2', '90', '0.5'],
        'Caixa de pizza' => ['un', '200', '300', '50'],
        'Refrigerante 2 L' => ['un', '24', '192', '12'],
        'Água 500 ml' => ['un', '48', '72', '12'],
        'Laranja' => ['kg', '6', '30', '2'],
    ];

    /** produto => [[insumo, quantidade, unidade], ...] */
    private const RECIPES = [
        'Margherita' => [['Massa de pizza', '300', 'g'], ['Molho de tomate', '100', 'g'], ['Muçarela', '200', 'g'], ['Caixa de pizza', '1', 'un']],
        'Calabresa' => [['Massa de pizza', '300', 'g'], ['Molho de tomate', '100', 'g'], ['Muçarela', '150', 'g'], ['Calabresa', '150', 'g'], ['Caixa de pizza', '1', 'un']],
        'Quatro queijos' => [['Massa de pizza', '300', 'g'], ['Molho de tomate', '80', 'g'], ['Muçarela', '150', 'g'], ['Provolone', '80', 'g'], ['Catupiry', '60', 'g'], ['Caixa de pizza', '1', 'un']],
        'Frango com catupiry' => [['Massa de pizza', '300', 'g'], ['Molho de tomate', '80', 'g'], ['Frango desfiado', '150', 'g'], ['Catupiry', '100', 'g'], ['Caixa de pizza', '1', 'un']],
        'Brigadeiro' => [['Massa de pizza', '300', 'g'], ['Chocolate', '200', 'g'], ['Caixa de pizza', '1', 'un']],
        'Banana com canela' => [['Massa de pizza', '300', 'g'], ['Banana', '250', 'g'], ['Caixa de pizza', '1', 'un']],
        'Refrigerante 2 L' => [['Refrigerante 2 L', '1', 'un']],
        'Água sem gás' => [['Água 500 ml', '1', 'un']],
        'Suco natural 500 ml' => [['Laranja', '600', 'g']],
    ];

    private const ADDON_RECIPES = [
        'Bacon' => [['Bacon', '50', 'g']],
        'Catupiry' => [['Catupiry', '80', 'g']],
        'Cheddar' => [['Muçarela', '80', 'g']],
    ];

    public function run(): void
    {
        $units = Unit::all()->keyBy('code');
        $ledger = app(StockLedger::class);
        $items = [];

        // Aditivo: insumos que já existem (pelo nome) são reaproveitados, sem lançar compra.
        foreach (self::ITEMS as $name => [$unit, $quantity, $total, $min]) {
            $items[$name] = StockItem::firstOrCreate(['name' => $name], ['unit_id' => $units[$unit]->id, 'min_quantity' => Quantity::parse($min)]);

            if ($items[$name]->wasRecentlyCreated) {
                $ledger->purchase($items[$name], Quantity::parse($quantity), Money::parse($total), null, 'Estoque inicial (demonstração)');
            }
        }

        $save = app(SaveRecipe::class);
        $lines = fn (array $recipe) => array_map(fn ($l) => [
            'stock_item_id' => $items[$l[0]]->id,
            'quantity' => Quantity::parse($l[1]),
            'unit_id' => $units[$l[2]]->id,
        ], $recipe);

        // Fichas já existentes não são sobrescritas.
        foreach (self::RECIPES as $productName => $recipe) {
            $product = Product::where('name', $productName)->first();

            if ($product && ! Recipe::where('product_id', $product->id)->exists()) {
                $save->handle($product, $lines($recipe));
            }
        }

        foreach (self::ADDON_RECIPES as $addonName => $recipe) {
            foreach (Addon::where('name', $addonName)->get() as $addon) {
                if (! Recipe::where('addon_id', $addon->id)->exists()) {
                    $save->handle($addon, $lines($recipe));
                }
            }
        }
    }
}
