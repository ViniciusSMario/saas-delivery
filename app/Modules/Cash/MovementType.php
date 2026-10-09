<?php

namespace App\Modules\Cash;

enum MovementType: string
{
    case Opening = 'opening';
    case Sale = 'sale';
    case Withdrawal = 'withdrawal';
    case Deposit = 'deposit';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Abertura (fundo de troco)',
            self::Sale => 'Recebimento',
            self::Withdrawal => 'Sangria',
            self::Deposit => 'Suprimento',
            self::Refund => 'Estorno',
        };
    }

    /** +1 entra no caixa, -1 sai. */
    public function sign(): int
    {
        return match ($this) {
            self::Opening, self::Sale, self::Deposit => 1,
            self::Withdrawal, self::Refund => -1,
        };
    }
}
