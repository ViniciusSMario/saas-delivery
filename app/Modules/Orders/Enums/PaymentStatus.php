<?php

namespace App\Modules\Orders\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'A receber',
            self::Paid => 'Pago',
            self::Refunded => 'Estornado',
        };
    }
}
