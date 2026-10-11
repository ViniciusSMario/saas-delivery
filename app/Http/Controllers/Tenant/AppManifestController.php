<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Establishment\AppIcon;
use App\Modules\Establishment\Models\Establishment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** App instalável (PWA) de cada restaurante: manifesto e ícones com o nome e o logo dele. */
class AppManifestController
{
    /** variante => [tamanho, desenho] */
    private const ICONS = [
        '192' => [192, 'any'],
        '512' => [512, 'any'],
        'maskable' => [512, 'maskable'],
        'apple' => [180, 'any'],
        'badge' => [96, 'badge'],
    ];

    public function manifest(AppIcon $icon): JsonResponse
    {
        $name = Establishment::current()->trade_name ?: tenant()->name;
        $version = $icon->version();

        return response()->json([
            'id' => '/',
            'name' => $name,
            'short_name' => Str::limit($name, 12, ''),
            'description' => "Mesas, pedidos e entregas de {$name}",
            'lang' => 'pt-BR',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f5f5f4',
            'theme_color' => '#ffffff',
            'icons' => [
                ['src' => "/app-icon/192.png?v={$version}", 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => "/app-icon/512.png?v={$version}", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => "/app-icon/maskable.png?v={$version}", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=300'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function icon(string $variant, AppIcon $icon): Response
    {
        [$size, $style] = self::ICONS[$variant] ?? abort(404);

        $png = Cache::remember(
            'app-icon:'.tenant()->getKey().":{$variant}:".$icon->version(),
            now()->addDay(),
            fn () => base64_encode($icon->png($size, $style)),
        );

        return response(base64_decode($png), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }
}
