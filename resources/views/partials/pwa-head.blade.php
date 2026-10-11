{{-- App instalável e notificações no celular: só no domínio de um restaurante. --}}
@if (tenant())
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/app-icon/apple.png?v={{ app(\App\Modules\Establishment\AppIcon::class)->version() }}">
    <meta name="theme-color" content="#ffffff">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ \App\Modules\Establishment\Models\Establishment::current()->trade_name ?: tenant()->name }}">
    <meta name="push-key" content="{{ config('push.vapid.public_key') }}">
    @auth('web')
        <meta name="push-user" content="{{ auth('web')->id() }}">
    @endauth
    @vite('resources/js/pwa.js')
@endif
