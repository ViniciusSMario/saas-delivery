<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Entrar' }} · {{ $brand ?? tenant()?->name ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-100 text-stone-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-6 text-center">
                <p class="text-xs font-semibold tracking-widest text-brand-600 uppercase">{{ $eyebrow ?? 'Sistema do estabelecimento' }}</p>
                <h1 class="mt-1 text-2xl font-semibold">{{ $brand ?? tenant()?->name ?? config('app.name') }}</h1>
            </div>

            <div class="card card-body">
                {{ $slot }}
            </div>
        </div>
    </main>
</body>
</html>
