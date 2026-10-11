<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** QR code em SVG (nítido na impressão em qualquer tamanho). */
class QrCode
{
    public static function svg(string $text, int $size = 240): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($text);

        // Sem a declaração XML: o SVG vai direto no HTML.
        return preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }
}
