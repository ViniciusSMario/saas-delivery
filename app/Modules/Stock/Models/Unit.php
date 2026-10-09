<?php

namespace App\Modules\Stock\Models;

use App\Support\Quantity;
use App\Tenancy\TenantModel;
use InvalidArgumentException;

class Unit extends TenantModel
{
    public const DEFAULTS = [
        'un' => ['Unidade', 'count', '1'],
        'kg' => ['Quilo', 'mass', '1000'],
        'g' => ['Grama', 'mass', '1'],
        'l' => ['Litro', 'volume', '1000'],
        'ml' => ['Mililitro', 'volume', '1'],
    ];

    protected $fillable = ['code', 'name', 'dimension', 'factor'];

    public static function seedDefaults(): void
    {
        foreach (self::DEFAULTS as $code => [$name, $dimension, $factor]) {
            static::firstOrCreate(['code' => $code], compact('name', 'dimension', 'factor'));
        }
    }

    public function compatibleWith(self $other): bool
    {
        return $this->dimension === $other->dimension;
    }

    /** Converte uma quantidade desta unidade para $to (ex.: 200 g → 0,200 kg). */
    public function convert(string $quantity, self $to): string
    {
        if (! $this->compatibleWith($to)) {
            throw new InvalidArgumentException("Não dá para converter {$this->code} em {$to->code}.");
        }

        return Quantity::div(Quantity::mul($quantity, (string) $this->factor, 6), (string) $to->factor);
    }
}
