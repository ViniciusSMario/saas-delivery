<?php

namespace App\Support;

/**
 * Ícones de traço (24×24), desenhados no mesmo estilo: o caminho SVG de cada nome.
 * No Blade: <x-icon name="home" class="size-5" />. No JavaScript (busca rápida), o mapa vai
 * junto com a página.
 */
final class Icons
{
    public const PATHS = [
        'home' => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
        'orders' => 'M9 4h6m-6 0a2 2 0 0 0-2 2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-1a2 2 0 0 0-2-2M9 12h6M9 16h4',
        'flame' => 'M12 3c1 3.5 5 5.5 5 10a5 5 0 0 1-10 0c0-2.5 1.5-4 2.5-5 .3 1.6 1.2 2.6 2.5 3 0-3-1-5.5 0-8z',
        'table' => 'M3 8h18M5 8v11M19 8v11M8 8V5h8v3',
        'pos' => 'M4 4h16v11H4zM9 19h6M12 15v4M8 8h3M8 11h8',
        'wallet' => 'M19 7V5a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4m0 4v3a1 1 0 0 1-1 1H5a2 2 0 0 1-2-2V6M21 12h-4a2 2 0 0 0 0 4h4z',
        'clock' => 'M12 7v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
        'book' => 'M12 6.5C10 5 7 4.5 3 5v13c4-.5 7 0 9 1.5m0-13c2-1.5 5-2 9-1.5v13c-4-.5-7 0-9 1.5m0-13v13',
        'tag' => 'M3 12V4h8l9 9-8 8zM7.5 7.5h.01',
        'plus-circle' => 'M12 8v8M8 12h8M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
        'gift' => 'M4 11h16v9H4zM3 7h18v4H3zM12 7v13M12 7c-1.5-3-5-3-5-1s3 1 5 1zm0 0c1.5-3 5-3 5-1s-3 1-5 1z',
        'users' => 'M16 20v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 20v-1a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
        'user' => 'M20 21v-1a5 5 0 0 0-5-5H9a5 5 0 0 0-5 5v1M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
        'trending' => 'M3 17l6-6 4 4 8-8M15 7h6v6',
        'chart' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
        'box' => 'M21 8l-9-5-9 5 9 5zM3 8v8l9 5 9-5V8M12 13v8',
        'file' => 'M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M8 13h8M8 17h5',
        'activity' => 'M3 12h4l3 8 4-16 3 8h4',
        'store' => 'M4 10v10h16V10M3 6l2-3h14l2 3v1a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0zM10 20v-5h4v5',
        'sliders' => 'M4 6h9M17 6h3M4 12h3M11 12h9M4 18h11M19 18h1M15 4v4M9 10v4M17 16v4',
        'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z',
        'list' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
        'search' => 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-4-4',
        'bag' => 'M6 7h12l1 13H5zM9 7a3 3 0 0 1 6 0',
        'info' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 11v5M12 8h.01',
        'logout' => 'M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 16l4-4-4-4M14 12H4',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'x' => 'M6 6l12 12M18 6 6 18',
        'chevron-left' => 'M15 18l-6-6 6-6',
        'chevron-right' => 'M9 18l6-6-6-6',
        'chevron-down' => 'M6 9l6 6 6-6',
        'plus' => 'M12 5v14M5 12h14',
        'minus' => 'M5 12h14',
        'check' => 'M5 12l5 5 9-10',
        'pin' => 'M12 21s-7-6.5-7-12a7 7 0 1 1 14 0c0 5.5-7 12-7 12zM12 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
        'phone' => 'M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2',
        'truck' => 'M3 6h11v10H3zM14 9h4l3 3v4h-7M7.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM17.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z',
        'expand' => 'M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5',
        'bell' => 'M6 16v-5a6 6 0 1 1 12 0v5l2 2H4zM10 21h4',
        'bell-off' => 'M6 16v-5a6 6 0 0 1 9.5-4.9M18 11v5l2 2H8M10 21h4M3 3l18 18',
        'alert' => 'M12 3 2 20h20zM12 10v4M12 17h.01',
        'printer' => 'M7 9V3h10v6M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M7 14h10v7H7z',
        'download' => 'M12 4v12M7 11l5 5 5-5M5 20h14',
        'building' => 'M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16M16 9h2a2 2 0 0 1 2 2v10M8 7h4M8 11h4M8 15h4M3 21h18',
        'external' => 'M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5',
        'utensils' => 'M7 3v8M4 3v5a3 3 0 0 0 6 0V3M7 11v10M17 3c-2 2-3 4-3 7h3v11',
        'calendar' => 'M4 6h16v15H4zM4 10h16M8 3v4M16 3v4',
        'cash' => 'M3 7h18v10H3zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM6 10v.01M18 14v.01',
        'edit' => 'M4 20h4L19 9l-4-4L4 16zM14 6l4 4',
        'trash' => 'M4 7h16M10 11v6M14 11v6M5 7l1 13h12l1-13M9 7V4h6v3',
        'card' => 'M3 6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1zM3 10h18M7 15h4',
        'lock' => 'M6 11h12v10H6zM8 11V7a4 4 0 0 1 8 0v4',
        'share' => 'M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7M16 6l-4-4-4 4M12 2v13',
        'eye' => 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
        'filter' => 'M3 5h18l-7 8v6l-4 2v-8z',
        'grid' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
        'refresh' => 'M20 11a8 8 0 0 0-14.9-3.9M4 5v4h4M4 13a8 8 0 0 0 14.9 3.9M20 19v-4h-4',
        'volume' => 'M4 9h4l5-4v14l-5-4H4zM16.5 8.5a5 5 0 0 1 0 7M19 6a8.5 8.5 0 0 1 0 12',
        'volume-off' => 'M4 9h4l5-4v14l-5-4H4zM17 9.5l5 5M22 9.5l-5 5',
        'ticket' => 'M3 7a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v3a2 2 0 0 0 0 4v3a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-3a2 2 0 0 0 0-4zM14 6v2M14 11v2M14 16v2',
        'copy' => 'M8 8h11a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1zM16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3',
        'star' => 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z',
        'qr' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2v2h-2zM14 18h2v2h-2zM18 18h2v2h-2zM16 16h2v2h-2z',
        'hand' => 'M8 13V5.5a1.5 1.5 0 0 1 3 0V12M11 11.5v-7a1.5 1.5 0 0 1 3 0v7M14 11.5V6a1.5 1.5 0 0 1 3 0v7.5M8 12.5V10a1.5 1.5 0 0 0-3 0v4a7 7 0 0 0 7 7h1a6 6 0 0 0 6-6v-5a1.5 1.5 0 0 0-3 0',
        'pause' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM10 9v6M14 9v6',
        'play' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM10 8.5v7l5.5-3.5z',
        'message' => 'M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1h-9l-5 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM8 10h8M8 13h5',
        'user-off' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 12.5-6.6M17 17l4 4M21 17l-4 4',
    ];

    public static function path(string $name): string
    {
        return self::PATHS[$name] ?? self::PATHS['info'];
    }
}
