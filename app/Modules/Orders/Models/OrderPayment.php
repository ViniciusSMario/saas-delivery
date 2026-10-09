<?php

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Enums\PaymentStatus;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPayment extends TenantModel
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_cents' => MoneyCast::class,
            'change_for_cents' => MoneyCast::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /** Troco a levar (só dinheiro). */
    public function change(): ?Money
    {
        return $this->change_for_cents?->greaterThan($this->amount_cents)
            ? $this->change_for_cents->subtract($this->amount_cents)
            : null;
    }
}
