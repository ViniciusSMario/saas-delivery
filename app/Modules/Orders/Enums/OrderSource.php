<?php

namespace App\Modules\Orders\Enums;

enum OrderSource: string
{
    case Online = 'online';
    case Counter = 'counter';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Cardápio online',
            self::Counter => 'Balcão',
        };
    }
}
