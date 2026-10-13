{{--
    "Modo app" do garçom e do entregador: feito para o celular, com abas grandes
    embaixo e nada além do que eles usam. Telas de detalhe (comanda) escondem as abas
    e têm a própria barra de ações.
--}}
@php
    $user = auth()->user();
    $establishment = \App\Modules\Establishment\Models\Establishment::current();
    $detail = request()->routeIs('tables.command');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Início' }} · {{ tenant()->name }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pwa-head')
</head>
<body class="min-h-screen bg-stone-100">
    @unless ($detail)
        <header class="print:hidden sticky top-0 z-20 border-b border-stone-200 bg-white/95 pt-[env(safe-area-inset-top)] backdrop-blur">
            <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-2.5">
                @if ($establishment->logo_path)
                    <img src="{{ route('establishment.logo') }}" alt="" class="size-9 rounded-xl bg-white object-contain ring-1 ring-stone-200">
                @else
                    <span class="flex size-9 items-center justify-center rounded-xl bg-brand-600 font-bold text-white">{{ mb_strtoupper(mb_substr(tenant()->name, 0, 1)) }}</span>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="truncate text-base leading-tight font-bold">{{ $title ?? 'Início' }}</p>
                    <p class="truncate text-xs text-stone-500">{{ $establishment->trade_name ?: tenant()->name }}</p>
                </div>
                <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                    <button type="button" @click="open = ! open" class="flex size-10 items-center justify-center rounded-full bg-brand-50 text-sm font-bold text-brand-700" aria-label="Minha conta">
                        {{ $user->initials() }}
                    </button>
                    <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open = false" class="absolute right-0 z-30 mt-2 w-60 overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-xl">
                        <div class="border-b border-stone-100 px-4 py-3">
                            <p class="truncate font-semibold">{{ $user->name }}</p>
                            <p class="truncate text-xs text-stone-500">{{ $user->roles->pluck('name')->implode(', ') }}</p>
                        </div>
                        <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-3 px-4 py-3 text-sm font-medium hover:bg-stone-50"><x-icon name="user" class="size-5 text-stone-400" /> Minha conta</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-3 px-4 py-3 text-sm font-medium text-red-700 hover:bg-red-50"><x-icon name="logout" class="size-5" /> Sair</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
    @endunless

    @if ($support = session('support_access'))
        <div class="print:hidden flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-violet-700 px-4 py-2 text-sm text-white">
            <x-icon name="shield" class="size-4" />
            <span>Modo suporte: <strong>{{ $support['email'] }}</strong> usando a conta de {{ $support['user'] }}.</span>
            <form method="POST" action="{{ route('support.leave') }}">
                @csrf
                <button type="submit" class="rounded-lg bg-white/15 px-2.5 py-1 font-semibold hover:bg-white/25">Sair do modo suporte</button>
            </form>
        </div>
    @endif

    <main @class(['mx-auto max-w-5xl px-4', 'pt-4 pb-28' => ! $detail, 'pb-6' => $detail])>
        @include('partials.toasts-flash')

        @if (! $detail && ! request()->routeIs('profile') && \App\Modules\Push\StaffAlerts::receivesAlerts($user))
            @include('partials.push-banner', ['what' => 'quando um pedido ficar pronto'])
        @endif

        {{ $slot }}
    </main>

    @unless ($detail)
        <livewire:tenant.field-nav />
    @endunless
</body>
</html>
