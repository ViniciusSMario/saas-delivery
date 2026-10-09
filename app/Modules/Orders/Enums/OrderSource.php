<?php

namespace App\Modules\Orders\Enums;

enum OrderSource: string
{
    case Online = 'online';
    case Counter = 'counter';
    case Table = 'table';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Cardápio online',
            self::Counter => 'Balcão',
            self::Table => 'Mesa',
        };
    }
}
