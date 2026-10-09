<?php

namespace App\Modules\Stock\Models;

use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeItem extends TenantModel
{
    public $timestamps = false;

    protected $fillable = ['recipe_id', 'stock_item_id', 'quantity', 'unit_id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
