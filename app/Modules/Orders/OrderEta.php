<?php

namespace App\Modules\Orders;

use App\Modules\Delivery\Models\DeliveryZone;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Models\Order;
use Carbon\CarbonImmutable;

/**
 * Previsão que o cliente vê (cardápio e acompanhamento do pedido): tempo de entrega ou de
 * retirada das Configurações; na entrega por bairro, vale o tempo do bairro quando houver.
 */
class OrderEta
{
    public const DELIVERY_SETTING = 'eta_delivery_minutes';

    public const PICKUP_SETTING = 'eta_pickup_minutes';

    public const DELIVERY_DEFAULT = 45;

    public const PICKUP_DEFAULT = 20;

    /** Minutos configurados (null = o restaurante não mostra previsão). */
    public static function minutes(Fulfillment $fulfillment): ?int
    {
        $minutes = match ($fulfillment) {
            Fulfillment::Delivery => (int) Setting::read(self::DELIVERY_SETTING, self::DELIVERY_DEFAULT),
            Fulfillment::Pickup => (int) Setting::read(self::PICKUP_SETTING, self::PICKUP_DEFAULT),
            default => 0,
        };

        return $minutes > 0 ? $minutes : null;
    }

    /** Previsão para quem pede agora: a configurada mais o tempo extra da hora do sufoco. */
    public static function now(Fulfillment $fulfillment): ?int
    {
        $minutes = self::minutes($fulfillment);

        return $minutes ? $minutes + RushMode::extraMinutes() : null;
    }

    /** Horário previsto para o pedido ficar pronto para retirada ou chegar ao cliente. */
    public static function for(Order $order): ?CarbonImmutable
    {
        $minutes = self::minutes($order->fulfillment);

        if ($minutes && $order->fulfillment === Fulfillment::Delivery && $order->delivery_zone_id) {
            $minutes = DeliveryZone::whereKey($order->delivery_zone_id)->value('eta_minutes') ?: $minutes;
        }

        // O tempo extra é o de quando o pedido entrou: voltar ao normal não adianta a previsão dele.
        return $minutes ? CarbonImmutable::parse($order->created_at)->addMinutes($minutes + (int) $order->eta_extra_minutes) : null;
    }
}
