<?php

namespace App\Modules\Promotions\Models;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Prêmio do cartão fidelidade: cupom pessoal gerado quando o cliente completa a meta de pedidos. */
class LoyaltyReward extends TenantModel
{
    public const UPDATED_AT = null;

    protected $fillable = ['customer_id', 'coupon_id', 'order_id', 'stamps'];

    protected function casts(): array
    {
        return ['stamps' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
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
