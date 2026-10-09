<?php

namespace App\Modules\Stock\Models;

use App\Modules\Menu\Models\Addon;
use App\Modules\Menu\Models\Product;
use App\Support\Money;
use App\Support\Quantity;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ficha técnica de um produto ou de um adicional. */
class Recipe extends TenantModel
{
    protected $fillable = ['product_id', 'addon_id'];

    public function items(): HasMany
    {
        return $this->hasMany(RecipeItem::class)->orderBy('id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    /** Custo pelo custo médio atual dos insumos. */
    public function cost(): Money
    {
        $cents = '0';

        foreach ($this->items as $item) {
            $inItemUnit = $item->unit->convert($item->quantity, $item->stockItem->unit);
            $cents = Quantity::add($cents, Quantity::mul($inItemUnit, (string) $item->stockItem->average_cost_cents, 6));
        }

        return Money::cents((int) Quantity::round($cents));
    }
}
