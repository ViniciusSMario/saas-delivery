<?php

namespace App\Modules\Printing;

use Illuminate\Support\Str;

/**
 * Converte as linhas de um cupom em comandos ESC/POS (padrão das impressoras térmicas
 * Epson, Elgin, Bematech, Daruma...). Os acentos saem sem acento: várias impressoras
 * baratas não têm a página de código certa.
 */
final class EscPos
{
    private const ESC = "\x1B";

    private const GS = "\x1D";

    /** @param  list<array{text: string, style: string, align: string}>  $lines */
    public static function encode(array $lines, int $copies = 1): string
    {
        $one = self::ESC.'@'; // reinicia

        foreach ($lines as $line) {
            $one .= self::ESC.'a'.($line['align'] === 'center' ? "\x01" : "\x00");
            $one .= self::ESC.'E'.(in_array($line['style'], ['bold', 'big'], true) ? "\x01" : "\x00");
            $one .= self::GS.'!'.($line['style'] === 'big' ? "\x11" : "\x00");
            $one .= Str::ascii($line['text'])."\n";
        }

        // Volta ao normal, avança o papel e corta (parcial).
        $one .= self::GS."!\x00".self::ESC."E\x00".self::ESC."a\x00"."\n\n\n".self::GS."V\x42\x00";

        return str_repeat($one, max(1, $copies));
    }
}
