<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} · {{ config('app.name') }} Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-100 text-stone-900 antialiased">
    <header class="bg-stone-900 text-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="{{ route('platform.tenants.index') }}" wire:navigate class="font-semibold">
                {{ config('app.name') }} <span class="badge ml-1 bg-brand-600 text-white">Admin</span>
            </a>
            <div class="flex items-center gap-3 text-sm">
                <span class="hidden text-stone-300 sm:block">{{ auth('platform')->user()->name }} · {{ auth('platform')->user()->role }}</span>
                <form method="POST" action="{{ route('platform.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-stone-600 px-3 py-1.5 hover:bg-stone-800">Sair</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
        @if (session('status'))
            <div class="alert-success mb-6">{{ session('status') }}</div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
