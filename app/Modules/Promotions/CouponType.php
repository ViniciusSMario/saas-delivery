<?php

namespace App\Modules\Promotions;

enum CouponType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case FreeDelivery = 'free_delivery';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Porcentagem',
            self::Fixed => 'Valor fixo',
            self::FreeDelivery => 'Entrega grátis',
        };
    }
}
