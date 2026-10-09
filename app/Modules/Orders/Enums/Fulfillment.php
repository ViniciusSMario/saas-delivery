<?php

namespace App\Modules\Orders\Enums;

enum Fulfillment: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';
    case Counter = 'counter';

    public function label(): string
    {
        return match ($this) {
            self::Delivery => 'Entrega',
            self::Pickup => 'Retirada',
            self::Counter => 'Balcão',
        };
    }
}
