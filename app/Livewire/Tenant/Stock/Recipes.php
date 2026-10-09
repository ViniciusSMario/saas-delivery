<?php

namespace App\Livewire\Tenant\Stock;

use App\Modules\Menu\Models\AddonGroup;
use App\Modules\Menu\Models\Product;
use App\Modules\Stock\Models\Recipe;
use App\Support\Money;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Visão geral das fichas técnicas: custo, preço e margem de cada produto (CMV). */
#[Title('Fichas técnicas')]
class Recipes extends Component
{
    public function render()
    {
        $recipes = Recipe::with(['items.stockItem.unit', 'items.unit'])->get();
        $byProduct = $recipes->whereNotNull('product_id')->keyBy('product_id');
        $byAddon = $recipes->whereNotNull('addon_id')->keyBy('addon_id');

        $products = Product::with('category')->orderBy('name')->get()->map(function (Product $product) use ($byProduct) {
            $recipe = $byProduct->get($product->id);
            $cost = $recipe?->cost();
            $margin = $cost && $product->price_cents->cents > 0
                ? round(($product->price_cents->cents - $cost->cents) / $product->price_cents->cents * 100, 1)
                : null;

            return compact('product', 'recipe', 'cost', 'margin');
        });

        return view('livewire.tenant.stock.recipes', [
            'products' => $products,
            'withRecipe' => $products->whereNotNull('recipe')->count(),
            'groups' => AddonGroup::with('addons')->orderBy('name')->get(),
            'addonCosts' => $byAddon->map(fn (Recipe $r) => $r->cost()),
            'zero' => Money::zero(),
        ]);
    }
}
