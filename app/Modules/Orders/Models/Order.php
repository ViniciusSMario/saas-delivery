<?php

namespace App\Modules\Orders\Models;

use App\Modules\Access\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Support\Casts\MoneyCast;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends TenantModel
{
    protected $guarded = ['id', 'number'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'source' => OrderSource::class,
            'fulfillment' => Fulfillment::class,
            'status' => OrderStatus::class,
            'subtotal_cents' => MoneyCast::class,
            'discount_cents' => MoneyCast::class,
            'delivery_fee_cents' => MoneyCast::class,
            'total_cents' => MoneyCast::class,
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'runner_claimed_at' => 'datetime',
        ];
    }

    /** Garçom que vai levar o pedido de mesa pronto até a mesa. */
    public function runner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'runner_id');
    }

    /** Quem levou o pedido ao último status (ex.: quem entregou ou serviu). */
    public function lastActorName(): ?string
    {
        $userId = $this->history()->reorder('id', 'desc')->value('user_id');

        return $userId ? User::find($userId)?->name : null;
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(OrderReview::class);
    }

    /** O cliente ainda pode avaliar: concluído há no máximo 7 dias e sem avaliação. */
    public function canBeReviewed(): bool
    {
        return $this->status === OrderStatus::Completed
            && $this->completed_at?->greaterThan(now()->subDays(OrderReview::WINDOW_DAYS))
            && ! $this->review()->exists();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class)->orderBy('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Entregador que saiu com o pedido. */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', array_map(fn (OrderStatus $s) => $s->value, OrderStatus::active()));
    }

    public function isPaid(): bool
    {
        return $this->payments->isNotEmpty() && $this->payments->every(fn (OrderPayment $p) => $p->status === PaymentStatus::Paid);
    }

    public function address(): string
    {
        $line = collect([$this->address_street, $this->address_number])->filter()->implode(', ');

        return collect([$line, $this->address_complement, $this->address_district, $this->address_city])->filter()->implode(' · ');
    }
}
