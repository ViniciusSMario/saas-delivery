<?php

namespace App\Modules\Orders\Models;

use App\Modules\Menu\Models\Combo;
use App\Modules\Menu\Models\Product;
use App\Support\Casts\MoneyCast;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Retrato do item no momento da venda: mudar o cardápio depois não altera o pedido. */
class OrderItem extends TenantModel
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'unit_price_cents' => MoneyCast::class,
            'addons_cents' => MoneyCast::class,
            'total_cents' => MoneyCast::class,
            'quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(OrderItemAddon::class)->orderBy('id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class)->withTrashed();
    }
}
