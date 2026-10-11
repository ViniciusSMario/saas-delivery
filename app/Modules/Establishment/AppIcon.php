<?php

namespace App\Modules\Establishment;

use App\Modules\Establishment\Models\Establishment;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Ícone do app instalado no celular. Com logo: o logo centralizado em fundo branco.
 * Sem logo: uma cloche (prato com tampa) branca na cor da marca.
 * Variantes: "any" (com margem normal), "maskable" (o Android recorta em círculo:
 * desenho dentro dos 80% centrais) e "badge" (silhueta branca em fundo transparente,
 * para a barra de notificações do Android).
 */
class AppIcon
{
    private const BRAND = [217, 74, 24];

    public function png(int $size, string $variant = 'any'): string
    {
        // Desenha 4× maior e reduz: bordas suaves sem depender de antialias do GD.
        $scale = $size <= 256 ? 4 : 2;
        $canvas = $size * $scale;
        $image = imagecreatetruecolor($canvas, $canvas);
        imagesavealpha($image, true);
        imagealphablending($image, true);

        $logo = $variant === 'badge' ? null : $this->logo();

        if ($variant === 'badge') {
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
            $this->drawCloche($image, $canvas, 0.86, imagecolorallocate($image, 255, 255, 255));
        } elseif ($logo) {
            imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
            $this->drawLogo($image, $canvas, $logo, $variant === 'maskable' ? 0.62 : 0.8);
        } else {
            imagefill($image, 0, 0, imagecolorallocate($image, ...self::BRAND));
            $this->drawCloche($image, $canvas, $variant === 'maskable' ? 0.62 : 0.74, imagecolorallocate($image, 255, 255, 255));
        }

        $final = imagecreatetruecolor($size, $size);
        imagesavealpha($final, true);
        imagealphablending($final, false);
        imagefill($final, 0, 0, imagecolorallocatealpha($final, 0, 0, 0, 127));
        imagecopyresampled($final, $image, 0, 0, 0, 0, $size, $size, $canvas, $canvas);

        ob_start();
        imagepng($final, null, 9);

        return (string) ob_get_clean();
    }

    /** Chave do cache: muda quando o logo muda. */
    public function version(): string
    {
        return md5((string) Establishment::current()->logo_path);
    }

    private function logo(): ?GdImage
    {
        $path = Establishment::current()->logo_path;

        if (! $path || ! Storage::disk('tenant')->exists($path)) {
            return null;
        }

        $image = @imagecreatefromstring(Storage::disk('tenant')->get($path));

        return $image ?: null;
    }

    private function drawLogo(GdImage $image, int $canvas, GdImage $logo, float $fit): void
    {
        $width = imagesx($logo);
        $height = imagesy($logo);
        $ratio = min($canvas * $fit / $width, $canvas * $fit / $height);
        $w = (int) round($width * $ratio);
        $h = (int) round($height * $ratio);

        imagecopyresampled($image, $logo, (int) (($canvas - $w) / 2), (int) (($canvas - $h) / 2), 0, 0, $w, $h, $width, $height);
    }

    /** Cloche ocupando $extent da largura: cúpula, puxador e prato. */
    private function drawCloche(GdImage $image, int $canvas, float $extent, int $color): void
    {
        $unit = $canvas * $extent;
        $cx = (int) ($canvas / 2);
        $base = (int) ($canvas / 2 + $unit * 0.16);

        $domeWidth = (int) ($unit * 0.8);
        imagefilledarc($image, $cx, $base, $domeWidth, (int) ($domeWidth * 0.92), 180, 360, $color, IMG_ARC_PIE);

        $knob = (int) ($unit * 0.12);
        imagefilledellipse($image, $cx, (int) ($base - $domeWidth * 0.46 - $knob * 0.35), $knob, $knob, $color);

        $plateHalf = (int) ($unit * 0.5);
        $plateTop = (int) ($base + $unit * 0.04);
        $plateHeight = (int) ($unit * 0.09);
        imagefilledrectangle($image, $cx - $plateHalf + $plateHeight, $plateTop, $cx + $plateHalf - $plateHeight, $plateTop + $plateHeight, $color);
        imagefilledellipse($image, $cx - $plateHalf + $plateHeight, $plateTop + (int) ($plateHeight / 2), $plateHeight, $plateHeight, $color);
        imagefilledellipse($image, $cx + $plateHalf - $plateHeight, $plateTop + (int) ($plateHeight / 2), $plateHeight, $plateHeight, $color);
    }
}
