<?php

namespace App\Modules\Tables;

enum CommandStatus: string
{
    case Open = 'open';
    case Closing = 'closing';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberta',
            self::Closing => 'Conta pedida',
            self::Closed => 'Fechada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open || $this === self::Closing;
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return [self::Open->value, self::Closing->value];
    }
}
