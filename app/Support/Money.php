<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Dinheiro em centavos (inteiro), sem ponto flutuante. Moeda única: BRL.
 */
final class Money implements JsonSerializable, Stringable
{
    private function __construct(public readonly int $cents)
    {
    }

    public static function cents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Aceita o que se digita num campo de preço: "12", "12,5", "12,50", "1.234,56", "R$ 9,90".
     */
    public static function parse(string|int|float|null $value): self
    {
        if ($value === null || $value === '') {
            return self::zero();
        }

        if (is_int($value)) {
            return new self($value * 100);
        }

        $value = trim(str_replace(['R$', ' ', "\u{00A0}"], '', (string) $value));

        if (! preg_match('/^-?(\d{1,3}(\.\d{3})+|\d+)(,\d{1,2})?$|^-?\d+(\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException("Valor inválido: [{$value}].");
        }

        // Com vírgula, ou no formato "1.234": ponto é separador de milhar. Fora isso, ponto é decimal ("12.5").
        if (str_contains($value, ',') || preg_match('/^-?\d{1,3}(\.\d{3})+$/', $value)) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        $negative = str_starts_with($value, '-');
        [$int, $dec] = array_pad(explode('.', ltrim($value, '-')), 2, '0');
        $cents = (int) $int * 100 + (int) str_pad(substr($dec, 0, 2), 2, '0');

        return new self($negative ? -$cents : $cents);
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

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function multiply(int $quantity): self
    {
        return new self($this->cents * $quantity);
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->cents > $other->cents;
    }

    /** "R$ 1.234,56" */
    public function format(): string
    {
        return ($this->cents < 0 ? '-' : '').'R$ '.$this->decimal();
    }

    /** "1.234,56" (para campos de formulário) */
    public function decimal(): string
    {
        return number_format(abs($this->cents) / 100, 2, ',', '.');
    }

    public function __toString(): string
    {
        return $this->format();
    }

    public function jsonSerialize(): int
    {
        return $this->cents;
    }
}
