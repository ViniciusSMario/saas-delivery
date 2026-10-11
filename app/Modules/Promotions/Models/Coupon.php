<?php

namespace App\Modules\Promotions\Models;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Promotions\CouponType;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Cupom de desconto do cardápio online. O código fica sempre em maiúsculas. */
class Coupon extends TenantModel
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'description', 'type', 'percent', 'amount_cents', 'max_discount_cents', 'min_order_cents',
        'starts_at', 'ends_at', 'usage_limit', 'per_customer_limit', 'first_order_only', 'is_public', 'show_after_order', 'is_active',
    ];

    protected $attributes = ['is_active' => true, 'min_order_cents' => 0];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'percent' => 'integer',
            'amount_cents' => MoneyCast::class,
            'max_discount_cents' => MoneyCast::class,
            'min_order_cents' => MoneyCast::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
            'first_order_only' => 'boolean',
            'is_public' => 'boolean',
            'show_after_order' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** "PRIMEIRA10" (sem espaços, acentos ou símbolos). */
    public static function normalize(?string $code): string
    {
        return mb_strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string) $code));
    }

    public static function findByCode(?string $code): ?self
    {
        $code = self::normalize($code);

        return $code === '' ? null : self::where('code', $code)->first();
    }

    /** Cupom pessoal (prêmio da fidelidade): só este cliente usa. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /** Usos que contam para os limites: pedidos cancelados devolvem o cupom. */
    public function validRedemptions(): Builder
    {
        return CouponRedemption::query()
            ->where('coupon_id', $this->id)
            ->whereHas('order', fn ($q) => $q->where('status', '!=', OrderStatus::Cancelled->value));
    }

    /** Desconto sobre o subtotal dos itens; entrega grátis desconta a taxa de entrega. */
    public function discountFor(Money $subtotal, Money $deliveryFee): Money
    {
        $discount = match ($this->type) {
            CouponType::Percent => Money::cents(intdiv($subtotal->cents * (int) $this->percent, 100)),
            CouponType::Fixed => $this->amount_cents ?? Money::zero(),
            CouponType::FreeDelivery => $deliveryFee,
        };

        if ($this->type === CouponType::Percent && $this->max_discount_cents && $discount->greaterThan($this->max_discount_cents)) {
            $discount = $this->max_discount_cents;
        }

        // Nunca mais que o valor sobre o qual o cupom incide.
        $limit = $this->type === CouponType::FreeDelivery ? $deliveryFee : $subtotal;

        return $discount->greaterThan($limit) ? $limit : $discount;
    }

    /** "10% de desconto (até R$ 20,00)", "R$ 15,00 de desconto", "Entrega grátis". */
    public function benefit(): string
    {
        return match ($this->type) {
            CouponType::Percent => "{$this->percent}% de desconto".($this->max_discount_cents ? " (até {$this->max_discount_cents->format()})" : ''),
            CouponType::Fixed => "{$this->amount_cents?->format()} de desconto",
            CouponType::FreeDelivery => 'Entrega grátis',
        };
    }

    /** Benefício no meio de uma frase: "ganhe entrega grátis", "ganhe R$ 15,00 de desconto". */
    public function benefitInline(): string
    {
        return preg_replace('/^Entrega/u', 'entrega', $this->benefit());
    }

    /** Condições para mostrar ao cliente: "Pedidos a partir de R$ 50,00 · primeiro pedido · até 31/12". */
    public function conditions(string $timezone): string
    {
        return collect([
            $this->min_order_cents && ! $this->min_order_cents->isZero() ? "pedidos a partir de {$this->min_order_cents->format()}" : null,
            $this->first_order_only ? 'só no primeiro pedido' : null,
            $this->ends_at ? 'válido até '.$this->ends_at->timezone($timezone)->format('d/m') : null,
        ])->filter()->implode(' · ');
    }
}
