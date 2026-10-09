<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    {{ $slot }}

    {{-- Só para ter o Alpine (vem junto do Livewire). As páginas públicas não fazem
         requisições Livewire: funcionam também em meusistema.com/cardapio/{slug}. --}}
    @livewireScripts
</body>
</html>
