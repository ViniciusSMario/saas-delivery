@props(['title' => null])
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#d4461b">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-stone-900 antialiased">
    <header class="sticky top-0 z-30 border-b border-stone-200/70 bg-white/90 backdrop-blur-lg">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-extrabold tracking-tight">
                <span class="flex size-9 items-center justify-center rounded-xl bg-brand-600 text-white"><x-icon name="utensils" class="size-5" /></span>
                {{ config('app.name') }}
            </a>
            <nav class="flex items-center gap-2 text-sm">
                <a href="{{ route('home') }}#planos" class="btn-ghost hidden sm:inline-flex">Planos</a>
                <a href="{{ route('signup.create') }}" class="btn-primary">Teste grátis</a>
            </nav>
        </div>
    </header>

    {{ $slot }}

    <footer class="border-t border-stone-200 bg-stone-50">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-8 text-sm text-stone-500 sm:px-6">
            <p>© {{ now()->year }} {{ config('app.name') }}. Sistema para restaurantes, pizzarias e lanchonetes.</p>
            <a href="{{ route('signup.create') }}" class="btn-link">Criar conta</a>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
