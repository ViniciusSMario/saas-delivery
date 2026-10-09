<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Quantidades de estoque como string decimal (numeric(14,3) no banco), com bcmath:
 * nada de ponto flutuante em kg, litros e unidades.
 */
final class Quantity
{
    public const SCALE = 3;

    /** Aceita "1,25", "0,5", "2", "1.250,5" (milhar com vírgula) e "1.25" (ponto decimal, teclado do celular). */
    public static function parse(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '0.000';
        }

        if (is_int($value)) {
            return bcadd((string) $value, '0', self::SCALE);
        }

        $value = trim(str_replace(' ', '', (string) $value));

        if (! preg_match('/^-?(\d{1,3}(\.\d{3})+|\d+)(,\d+)?$|^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("Quantidade inválida: [{$value}].");
        }

        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return bcadd($value, '0', self::SCALE);
    }

    public static function isValid(string|int|float|null $value): bool
    {
        try {
            self::parse($value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    public static function mul(string $a, string $b, int $scale = self::SCALE): string
    {
        return bcmul($a, $b, $scale);
    }

    public static function div(string $a, string $b, int $scale = self::SCALE): string
    {
        return bccomp($b, '0', 6) === 0 ? '0' : bcdiv($a, $b, $scale);
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, self::SCALE);
    }

    public static function isPositive(string $a): bool
    {
        return self::compare($a, '0') > 0;
    }

    public static function negate(string $a): string
    {
        return bcmul($a, '-1', self::SCALE);
    }

    /** Arredonda meio para cima na escala pedida (bcmath só trunca). */
    public static function round(string $a, int $scale = 0): string
    {
        $half = '0.'.str_repeat('0', $scale).'5';

        return bcadd($a, str_starts_with($a, '-') ? '-'.$half : $half, $scale);
    }

    /** "1,25" (sem zeros à direita), para exibir. */
    public static function format(string $a): string
    {
        $negative = str_starts_with($a, '-');
        [$int, $dec] = array_pad(explode('.', ltrim($a, '-')), 2, '');
        $dec = rtrim($dec, '0');

        return ($negative ? '-' : '').number_format((int) $int, 0, ',', '.').($dec !== '' ? ','.$dec : '');
    }
}
