<?php

namespace App\Modules\Tables\Models;

use App\Modules\Access\Models\User;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Tables\CommandStatus;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Conta de uma mesa ou avulsa. Os itens são os pedidos lançados nela. */
class Command extends TenantModel
{
    protected $guarded = ['id', 'number'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => CommandStatus::class,
            'people' => 'integer',
            'service_fee_enabled' => 'boolean',
            'subtotal_cents' => MoneyCast::class,
            'service_fee_cents' => MoneyCast::class,
            'discount_cents' => MoneyCast::class,
            'total_cents' => MoneyCast::class,
            'closed_at' => 'datetime',
        ];
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CashMovement::class)->orderBy('id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', CommandStatus::openValues());
    }

    public function displayName(): string
    {
        return $this->diningTable ? 'Mesa '.$this->diningTable->name : ($this->label ?: 'Comanda #'.$this->number);
    }

    /** Soma dos pedidos não cancelados. */
    public function itemsSubtotal(): Money
    {
        return Money::cents((int) $this->orders()->where('status', '!=', OrderStatus::Cancelled->value)->sum('total_cents'));
    }

    public static function serviceFeePercent(): int
    {
        return max(0, min(30, (int) Setting::read('service_fee_percent', 10)));
    }

    public static function serviceFeeFor(Money $subtotal, bool $enabled): Money
    {
        return $enabled ? Money::cents((int) round($subtotal->cents * self::serviceFeePercent() / 100)) : Money::zero();
    }
}
