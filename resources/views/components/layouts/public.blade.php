@props(['title' => null, 'head' => null])
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#d4461b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    {{ $head }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- Casca de app: no celular ocupa a tela; no computador vira uma coluna centralizada. --}}
<body class="min-h-screen bg-stone-200/70 text-stone-900 antialiased">
    <div class="relative mx-auto min-h-screen max-w-xl bg-stone-50 sm:shadow-xl sm:shadow-stone-900/5">
        {{ $slot }}
    </div>

    {{-- Só para ter o Alpine (vem junto do Livewire). As páginas públicas não fazem
         requisições Livewire: funcionam também em meusistema.com/cardapio/{slug}. --}}
    @livewireScripts
</body>
</html>
