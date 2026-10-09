@php
    $nav = [
        ['route' => 'dashboard', 'label' => 'Início', 'can' => null],
        ['section' => 'Atendimento', 'can' => ['orders.view', 'pos.use', 'tables.manage', 'cash.operate']],
        ['route' => 'orders.board', 'label' => 'Painel de pedidos', 'can' => 'orders.view', 'match' => ['orders.board', 'orders.show']],
        ['route' => 'tables.map', 'label' => 'Mesas', 'can' => 'tables.manage', 'match' => ['tables.map', 'tables.command']],
        ['route' => 'orders.counter', 'label' => 'PDV', 'can' => 'pos.use'],
        ['route' => 'cash.current', 'label' => 'Caixa', 'can' => 'cash.operate', 'match' => ['cash.*']],
        ['route' => 'orders.index', 'label' => 'Histórico', 'can' => 'orders.view', 'match' => ['orders.index']],
        ['section' => 'Cardápio', 'can' => 'menu.manage'],
        ['route' => 'menu.products.index', 'label' => 'Produtos', 'can' => 'menu.manage'],
        ['route' => 'menu.categories', 'label' => 'Categorias', 'can' => 'menu.manage'],
        ['route' => 'menu.addon-groups.index', 'label' => 'Adicionais', 'can' => 'menu.manage'],
        ['route' => 'menu.combos.index', 'label' => 'Combos', 'can' => 'menu.manage'],
        ['section' => 'Gestão', 'can' => ['customers.manage', 'finance.view', 'reports.view']],
        ['route' => 'customers.index', 'label' => 'Clientes', 'can' => 'customers.manage', 'match' => ['customers.*']],
        ['route' => 'finance.index', 'label' => 'Financeiro', 'can' => 'finance.view', 'match' => ['finance.*']],
        ['route' => 'reports.index', 'label' => 'Relatórios', 'can' => 'reports.view', 'match' => ['reports.*']],
        ['section' => 'Estoque', 'can' => 'stock.manage'],
        ['route' => 'stock.index', 'label' => 'Insumos', 'can' => 'stock.manage', 'match' => ['stock.index', 'stock.show']],
        ['route' => 'stock.recipes', 'label' => 'Fichas técnicas', 'can' => 'stock.manage', 'match' => ['stock.recipes', 'stock.recipe.*']],
        ['route' => 'stock.consumption', 'label' => 'Consumo', 'can' => 'stock.manage', 'match' => ['stock.consumption']],
        ['section' => 'Administração', 'can' => ['establishment.manage', 'settings.manage', 'users.view', 'roles.manage', 'audit.view']],
        ['route' => 'establishment.edit', 'label' => 'Estabelecimento', 'can' => 'establishment.manage'],
        ['route' => 'settings.edit', 'label' => 'Configurações', 'can' => 'settings.manage'],
        ['route' => 'users.index', 'label' => 'Usuários', 'can' => 'users.view'],
        ['route' => 'roles.index', 'label' => 'Perfis e permissões', 'can' => 'roles.manage'],
        ['route' => 'audit.index', 'label' => 'Auditoria', 'can' => 'audit.view'],
    ];
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Início' }} · {{ tenant()->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-100 text-stone-900 antialiased" x-data="{ menu: false }">
    <div class="lg:flex">
        <aside class="print:hidden fixed inset-y-0 left-0 z-30 w-64 -translate-x-full border-r border-stone-200 bg-white transition-transform lg:static lg:min-h-screen lg:translate-x-0"
               :class="menu && 'translate-x-0'">
            <div class="border-b border-stone-200 px-5 py-4">
                <p class="text-xs font-semibold tracking-widest text-brand-600 uppercase">Estabelecimento</p>
                <p class="truncate font-semibold">{{ tenant()->name }}</p>
            </div>

            <nav class="space-y-1 p-3">
                @foreach ($nav as $item)
                    @if (isset($item['section']))
                        @if (! $item['can'] || $user->canAny((array) $item['can']))
                            <p class="px-3 pt-4 pb-1 text-xs font-semibold tracking-wide text-stone-400 uppercase">{{ $item['section'] }}</p>
                        @endif
                    @elseif (! $item['can'] || $user->can($item['can']))
                        <a href="{{ route($item['route']) }}" wire:navigate
                           @class([
                               'block rounded-lg px-3 py-2 text-sm font-medium',
                               'bg-brand-50 text-brand-700' => request()->routeIs(...($item['match'] ?? [$item['route'], str_replace('.index', '.*', $item['route'])])),
                               'text-stone-600 hover:bg-stone-50 hover:text-stone-900' => ! request()->routeIs(...($item['match'] ?? [$item['route'], str_replace('.index', '.*', $item['route'])])),
                           ])>{{ $item['label'] }}</a>
                    @endif
                @endforeach
            </nav>

            <p class="px-5 pt-4 text-xs text-stone-400">Pedidos, PDV e estoque chegam nas próximas fases.</p>
        </aside>

        <div class="fixed inset-0 z-20 bg-stone-900/30 lg:hidden" x-show="menu" x-cloak @click="menu = false"></div>

        <div class="min-w-0 flex-1">
            <header class="print:hidden flex items-center justify-between gap-4 border-b border-stone-200 bg-white px-4 py-3 sm:px-6">
                <button type="button" class="btn-secondary px-3 lg:hidden" @click="menu = true" aria-label="Abrir menu">☰</button>

                <div class="ml-auto flex items-center gap-3">
                    <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-2 text-sm">
                        <span class="flex size-8 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">{{ $user->initials() }}</span>
                        <span class="hidden sm:block">
                            <span class="block font-medium leading-tight">{{ $user->name }}</span>
                            <span class="block text-xs text-stone-500">{{ $user->roles->pluck('name')->implode(', ') }}</span>
                        </span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn-secondary">Sair</button>
                    </form>
                </div>
            </header>

            <main class="mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-8">
                @if (session('status'))
                    <div class="alert-success mb-6">{{ session('status') }}</div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
