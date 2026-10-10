<?php

namespace App\Modules\Printing;

/**
 * Monta as linhas de um cupom térmico, independentes de onde ele vai sair
 * (HTML da estação de impressão ou ESC/POS na impressora de rede).
 */
final class Ticket
{
    /** @var list<array{text: string, style: string, align: string}> */
    private array $lines = [];

    public function __construct(public readonly int $columns = 48)
    {
    }

    public function big(string $text, string $align = 'center'): self
    {
        return $this->add($text, 'big', $align);
    }

    public function bold(string $text, string $align = 'left'): self
    {
        return $this->add($text, 'bold', $align);
    }

    public function line(string $text = '', string $align = 'left'): self
    {
        return $this->add($text, 'normal', $align);
    }

    /** Texto à esquerda e valor à direita na mesma linha (ex.: "Subtotal ...... R$ 40,00"). */
    public function pair(string $left, string $right, string $style = 'normal'): self
    {
        $space = max(1, $this->columns - mb_strlen($left) - mb_strlen($right));

        return $this->add($left.str_repeat(' ', $space).$right, $style, 'left');
    }

    public function rule(string $char = '-'): self
    {
        return $this->add(str_repeat($char, $this->columns), 'normal', 'left');
    }

    /** @return list<array{text: string, style: string, align: string}> */
    public function lines(): array
    {
        return $this->lines;
    }

    private function add(string $text, string $style, string $align): self
    {
        // Letra dupla ocupa o dobro da largura.
        $width = $style === 'big' ? intdiv($this->columns, 2) : $this->columns;

        foreach ($this->wrap($text, $width) as $piece) {
            $this->lines[] = ['text' => $piece, 'style' => $style, 'align' => $align];
        }

        return $this;
    }

    /** @return list<string> */
    private function wrap(string $text, int $width): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '' || mb_strlen($text) <= $width) {
            return [$text];
        }

        $indent = '';
        $pieces = [];
        $current = '';

        foreach (explode(' ', $text) as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if (mb_strlen($candidate) > $width && $current !== '') {
                $pieces[] = $current;
                $current = $indent.$word;
            } else {
                $current = $candidate;
            }
        }

        $pieces[] = $current;

        return array_map(fn ($p) => mb_substr($p, 0, $width), $pieces);
    }
}
