<?php

namespace App\Modules\Orders;

use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Models\Order;
use Carbon\CarbonImmutable;

/**
 * Pedidos do mês, para o limite brando do plano ("até N pedidos por mês"):
 * avisa o dono ao chegar perto e ao passar, mas nunca impede uma venda.
 */
class MonthlyOrders
{
    /** Avisos, em % do limite. */
    public const THRESHOLDS = [80, 100];

    public static function count(): int
    {
        return Order::query()->where('created_at', '>=', self::monthStart()->utc())->count();
    }

    /** null = plano sem limite (ou cortesia). */
    public static function limit(): ?int
    {
        return tenant()?->planLimit('orders_per_month');
    }

    /** Percentual usado (0 quando não há limite). */
    public static function percent(?int $count = null): int
    {
        $limit = self::limit();

        return $limit ? (int) floor(($count ?? self::count()) / $limit * 100) : 0;
    }

    public static function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::now(Setting::read('timezone', 'America/Sao_Paulo'))->startOfMonth();
    }
}
