<?php

namespace App\Modules\Delivery\Models;

use App\Support\Casts\MoneyCast;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Builder;

/** Bairro atendido, com a taxa de entrega (e, opcionalmente, pedido mínimo e tempo). */
class DeliveryZone extends TenantModel
{
    protected $fillable = ['name', 'fee_cents', 'min_order_cents', 'eta_minutes', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'fee_cents' => MoneyCast::class,
            'min_order_cents' => MoneyCast::class,
            'eta_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('name');
    }
}
