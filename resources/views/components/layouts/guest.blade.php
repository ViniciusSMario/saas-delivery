<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Entrar' }} · {{ $brand ?? tenant()?->name ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pwa-head')
</head>
<body class="min-h-screen text-stone-900 antialiased">
    <main class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-12">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-gradient-to-b from-brand-100/70 to-transparent"></div>

        <div class="relative w-full max-w-sm">
            <div class="mb-8 text-center">
                <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-brand-600 text-xl font-bold text-white shadow-float">
                    {{ mb_strtoupper(mb_substr($brand ?? tenant()?->name ?? config('app.name'), 0, 1)) }}
                </span>
                <p class="mt-4 text-xs font-semibold tracking-widest text-brand-600 uppercase">{{ $eyebrow ?? 'Sistema do estabelecimento' }}</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ $brand ?? tenant()?->name ?? config('app.name') }}</h1>
            </div>

            <div class="card p-6 sm:p-8">
                {{ $slot }}
            </div>
        </div>
    </main>
</body>
</html>
