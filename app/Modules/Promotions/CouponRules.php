<?php

namespace App\Modules\Promotions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Promotions\Models\Coupon;
use App\Support\Money;

/**
 * Quando um cupom pode ser usado. A mesma conferência serve à prévia do checkout e ao
 * pedido de verdade (PlaceOrder confere de novo com o cupom travado, por causa dos limites).
 */
class CouponRules
{
    /**
     * Motivo para recusar o cupom, ou null quando ele vale para este pedido.
     *
     * @param  bool  $requirePhone  pedido de verdade: cupom com limite por cliente precisa do telefone
     *                              (na prévia do checkout o cliente pode ainda não ter digitado)
     */
    public function problem(Coupon $coupon, Money $subtotal, ?string $phone = null, ?Fulfillment $fulfillment = null, bool $requirePhone = false): ?string
    {
        $timezone = Setting::read('timezone', 'America/Sao_Paulo');
        $phone = $phone ? Customer::normalizePhone($phone) : null;

        if (! $coupon->is_active) {
            return 'Este cupom não está mais ativo.';
        }

        if ($coupon->starts_at?->isFuture()) {
            return 'Este cupom vale a partir de '.$coupon->starts_at->timezone($timezone)->format('d/m').'.';
        }

        if ($coupon->ends_at?->isPast()) {
            return 'Este cupom expirou.';
        }

        // Prêmio da fidelidade: só o dono do cartão usa.
        if ($coupon->customer_id && $phone && Customer::withTrashed()->whereKey($coupon->customer_id)->value('phone') !== $phone) {
            return 'Este cupom é de outro cliente.';
        }

        if ($coupon->type === CouponType::FreeDelivery && $fulfillment && $fulfillment !== Fulfillment::Delivery) {
            return 'Este cupom é de entrega grátis: escolha entrega para usar.';
        }

        if ($coupon->min_order_cents && $coupon->min_order_cents->greaterThan($subtotal)) {
            return "Este cupom vale para pedidos a partir de {$coupon->min_order_cents->format()} (sem a taxa de entrega).";
        }

        if ($coupon->usage_limit !== null && $coupon->validRedemptions()->count() >= $coupon->usage_limit) {
            return 'Este cupom já esgotou.';
        }

        if ($requirePhone && ! $phone && ($coupon->per_customer_limit !== null || $coupon->first_order_only)) {
            return 'Informe o telefone do cliente para usar este cupom.';
        }

        if ($phone && $coupon->per_customer_limit !== null
            && $coupon->validRedemptions()->where('customer_phone', $phone)->count() >= $coupon->per_customer_limit) {
            return 'Você já usou este cupom.';
        }

        if ($phone && $coupon->first_order_only
            && Order::where('customer_phone', $phone)->where('status', '!=', OrderStatus::Cancelled->value)->exists()) {
            return 'Este cupom é só para o primeiro pedido.';
        }

        return null;
    }

    /** Cupom de "volte e ganhe" para oferecer a quem acabou de receber um pedido (se ainda puder usar). */
    public function returnOffer(?string $phone): ?Coupon
    {
        return Coupon::whereNull('customer_id')->where('show_after_order', true)->where('is_active', true)->orderBy('id')->get()
            ->first(fn (Coupon $coupon) => $this->problem($coupon, Money::cents(PHP_INT_MAX), $phone) === null);
    }

    /** Cupons divulgados no cardápio, válidos agora. */
    public function publicCoupons()
    {
        return Coupon::whereNull('customer_id')->where('is_public', true)->where('is_active', true)->orderBy('id')->get()
            ->filter(fn (Coupon $coupon) => ! $coupon->starts_at?->isFuture() && ! $coupon->ends_at?->isPast()
                && ($coupon->usage_limit === null || $coupon->validRedemptions()->count() < $coupon->usage_limit))
            ->values();
    }
}
