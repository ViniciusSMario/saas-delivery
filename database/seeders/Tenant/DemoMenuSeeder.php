<?php

namespace Database\Seeders\Tenant;

use App\Modules\Menu\Models\Addon;
use App\Modules\Menu\Models\AddonGroup;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Combo;
use App\Modules\Menu\Models\Product;
use App\Support\Money;
use Illuminate\Database\Seeder;

/**
 * Cardápio de demonstração (pizzaria). Só para desenvolvimento:
 * php artisan tenants:seed --tenant=empresa1 --class="Database\Seeders\Tenant\DemoMenuSeeder"
 */
class DemoMenuSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::withTrashed()->exists()) {
            $this->command?->warn('O cardápio já tem produtos; nada foi feito.');

            return;
        }

        $borda = $this->group('Escolha a borda', 1, 1, ['Tradicional' => '0', 'Catupiry' => '8', 'Cheddar' => '8', 'Chocolate' => '10']);
        $extras = $this->group('Adicionais', 0, 4, ['Bacon' => '6', 'Azeitona' => '3', 'Cebola caramelizada' => '5', 'Ovo' => '3']);
        $gelo = $this->group('Gelo e limão', 0, 2, ['Gelo' => '0', 'Limão' => '0']);

        $menu = [
            'Pizzas' => [
                ['101', 'Margherita', 'Molho de tomate, muçarela, tomate e manjericão.', '49,90', true, [$borda, $extras]],
                ['102', 'Calabresa', 'Calabresa fatiada, cebola e azeitona.', '52,90', true, [$borda, $extras]],
                ['103', 'Quatro queijos', 'Muçarela, provolone, parmesão e gorgonzola.', '58,90', false, [$borda, $extras]],
                ['104', 'Frango com catupiry', 'Frango desfiado temperado e catupiry original.', '56,90', false, [$borda, $extras]],
            ],
            'Pizzas doces' => [
                ['201', 'Brigadeiro', 'Chocolate ao leite e granulado.', '46,90', false, [$borda]],
                ['202', 'Banana com canela', 'Banana, açúcar e canela.', '44,90', false, [$borda]],
            ],
            'Bebidas' => [
                ['301', 'Refrigerante 2 L', 'Coca-Cola, Guaraná ou Sprite.', '14,00', false, [$gelo]],
                ['302', 'Suco natural 500 ml', 'Laranja, limão ou abacaxi.', '12,00', false, [$gelo]],
                ['303', 'Água sem gás', '500 ml.', '5,00', false, []],
            ],
        ];

        $products = [];

        foreach (array_keys($menu) as $position => $name) {
            $category = Category::create(['name' => $name, 'position' => $position + 1]);

            foreach ($menu[$name] as $i => [$code, $productName, $description, $price, $featured, $groups]) {
                $product = Product::create([
                    'category_id' => $category->id,
                    'code' => $code,
                    'name' => $productName,
                    'description' => $description,
                    'price_cents' => Money::parse($price),
                    'is_featured' => $featured,
                    'position' => $i + 1,
                ]);
                $product->addonGroups()->sync(collect($groups)->values()->mapWithKeys(fn ($g, $p) => [$g->id => ['position' => $p]])->all());
                $products[$code] = $product;
            }
        }

        $combo = Combo::create(['name' => 'Combo Família', 'description' => '2 pizzas salgadas + refrigerante 2 L.', 'price_cents' => Money::parse('109,90'), 'position' => 1]);
        $combo->items()->createMany([
            ['product_id' => $products['102']->id, 'quantity' => 1],
            ['product_id' => $products['101']->id, 'quantity' => 1],
            ['product_id' => $products['301']->id, 'quantity' => 1],
        ]);
    }

    /** @param  array<string, string>  $options */
    private function group(string $name, int $min, int $max, array $options): AddonGroup
    {
        $group = AddonGroup::create(['name' => $name, 'min_choices' => $min, 'max_choices' => $max]);
        $position = 0;

        foreach ($options as $option => $price) {
            Addon::create(['addon_group_id' => $group->id, 'name' => $option, 'price_cents' => Money::parse($price), 'position' => $position++]);
        }

        return $group;
    }
}
