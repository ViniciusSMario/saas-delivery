<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} · {{ config('app.name') }} Admin</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen text-stone-900 antialiased">
    <header class="sticky top-0 z-20 border-b border-white/10 bg-stone-950 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('platform.tenants.index') }}" wire:navigate class="flex items-center gap-3">
                <span class="flex size-9 items-center justify-center rounded-xl bg-brand-600">
                    <x-icon name="building" class="size-5" />
                </span>
                <span class="font-semibold">{{ config('app.name') }}</span>
                <span class="badge bg-white/10 text-brand-300">Admin</span>
            </a>
            <nav class="mr-auto flex gap-1 text-sm">
                @foreach (['platform.tenants.index' => ['Estabelecimentos', 'platform.tenants.*'], 'platform.billing.index' => ['Assinaturas', 'platform.billing.*']] as $route => [$label, $match])
                    <a href="{{ route($route) }}" wire:navigate @class(['rounded-lg px-3 py-1.5 font-medium transition', 'bg-white/10 text-white' => request()->routeIs($match), 'text-stone-400 hover:text-white' => ! request()->routeIs($match)])>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="flex items-center gap-3 text-sm">
                <span class="hidden text-right sm:block">
                    <span class="block font-medium">{{ auth('platform')->user()->name }}</span>
                    <span class="block text-xs text-stone-400">{{ auth('platform')->user()->role }}</span>
                </span>
                <form method="POST" action="{{ route('platform.logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm border border-white/15 text-stone-200 hover:bg-white/10">
                        <x-icon name="logout" class="size-4" /> Sair
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        @if (session('status'))
            <div class="alert-success mb-6">
                <x-icon name="check" class="mt-0.5 size-4" />
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
