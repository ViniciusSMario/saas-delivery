<?php

namespace App\Modules\Promotions\Models;

use App\Modules\Orders\Models\Order;
use App\Support\Casts\MoneyCast;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uso de um cupom em um pedido (um por pedido). */
class CouponRedemption extends TenantModel
{
    public const UPDATED_AT = null;

    protected $fillable = ['coupon_id', 'order_id', 'customer_phone', 'discount_cents'];

    protected function casts(): array
    {
        return ['discount_cents' => MoneyCast::class];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class)->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
