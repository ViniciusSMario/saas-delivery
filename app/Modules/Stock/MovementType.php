<?php

namespace App\Modules\Stock;

enum MovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Adjustment = 'adjustment';
    case Loss = 'loss';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Entrada (compra)',
            self::Sale => 'Venda',
            self::Adjustment => 'Ajuste de inventário',
            self::Loss => 'Perda',
        };
    }
}
