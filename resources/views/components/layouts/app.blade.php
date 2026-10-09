@php
    $nav = [
        ['route' => 'dashboard', 'label' => 'Início', 'icon' => 'home', 'can' => null],
        ['section' => 'Atendimento', 'can' => ['orders.view', 'pos.use', 'tables.manage', 'cash.operate']],
        ['route' => 'orders.board', 'label' => 'Painel de pedidos', 'icon' => 'flame', 'can' => 'orders.view', 'match' => ['orders.board', 'orders.show']],
        ['route' => 'tables.map', 'label' => 'Mesas', 'icon' => 'table', 'can' => 'tables.manage', 'match' => ['tables.map', 'tables.command']],
        ['route' => 'orders.counter', 'label' => 'PDV', 'icon' => 'pos', 'can' => 'pos.use'],
        ['route' => 'cash.current', 'label' => 'Caixa', 'icon' => 'wallet', 'can' => 'cash.operate', 'match' => ['cash.*']],
        ['route' => 'orders.index', 'label' => 'Histórico', 'icon' => 'clock', 'can' => 'orders.view', 'match' => ['orders.index']],
        ['section' => 'Cardápio', 'can' => 'menu.manage'],
        ['route' => 'menu.products.index', 'label' => 'Produtos', 'icon' => 'book', 'can' => 'menu.manage'],
        ['route' => 'menu.categories', 'label' => 'Categorias', 'icon' => 'tag', 'can' => 'menu.manage'],
        ['route' => 'menu.addon-groups.index', 'label' => 'Adicionais', 'icon' => 'plus-circle', 'can' => 'menu.manage'],
        ['route' => 'menu.combos.index', 'label' => 'Combos', 'icon' => 'gift', 'can' => 'menu.manage'],
        ['section' => 'Gestão', 'can' => ['customers.manage', 'finance.view', 'reports.view']],
        ['route' => 'customers.index', 'label' => 'Clientes', 'icon' => 'users', 'can' => 'customers.manage', 'match' => ['customers.*']],
        ['route' => 'finance.index', 'label' => 'Financeiro', 'icon' => 'trending', 'can' => 'finance.view', 'match' => ['finance.*']],
        ['route' => 'reports.index', 'label' => 'Relatórios', 'icon' => 'chart', 'can' => 'reports.view', 'match' => ['reports.*']],
        ['section' => 'Estoque', 'can' => 'stock.manage'],
        ['route' => 'stock.index', 'label' => 'Insumos', 'icon' => 'box', 'can' => 'stock.manage', 'match' => ['stock.index', 'stock.show']],
        ['route' => 'stock.recipes', 'label' => 'Fichas técnicas', 'icon' => 'file', 'can' => 'stock.manage', 'match' => ['stock.recipes', 'stock.recipe.*']],
        ['route' => 'stock.consumption', 'label' => 'Consumo', 'icon' => 'activity', 'can' => 'stock.manage', 'match' => ['stock.consumption']],
        ['section' => 'Administração', 'can' => ['establishment.manage', 'settings.manage', 'users.view', 'roles.manage', 'audit.view']],
        ['route' => 'establishment.edit', 'label' => 'Estabelecimento', 'icon' => 'store', 'can' => 'establishment.manage'],
        ['route' => 'settings.edit', 'label' => 'Configurações', 'icon' => 'sliders', 'can' => 'settings.manage'],
        ['route' => 'users.index', 'label' => 'Usuários', 'icon' => 'user', 'can' => 'users.view'],
        ['route' => 'roles.index', 'label' => 'Perfis e permissões', 'icon' => 'shield', 'can' => 'roles.manage'],
        ['route' => 'audit.index', 'label' => 'Auditoria', 'icon' => 'list', 'can' => 'audit.view'],
    ];
    $user = auth()->user();
    $establishment = \App\Modules\Establishment\Models\Establishment::current();
    $isActive = fn (array $item) => request()->routeIs(...($item['match'] ?? [$item['route'], str_replace('.index', '.*', $item['route'])]));
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Início' }} · {{ tenant()->name }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen" x-data="{ menu: false }" @keydown.escape.window="menu = false">
    <div class="lg:flex">
        {{-- Barra lateral --}}
        <aside class="print:hidden fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-stone-950 text-stone-300 transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:translate-x-0"
               :class="menu && 'translate-x-0'">
            <div class="flex items-center gap-3 px-5 py-5">
                @if ($establishment->logo_path)
                    <img src="{{ route('establishment.logo') }}" alt="" class="size-10 rounded-xl bg-white object-contain p-0.5">
                @else
                    <span class="flex size-10 items-center justify-center rounded-xl bg-brand-600 font-bold text-white">{{ mb_strtoupper(mb_substr(tenant()->name, 0, 1)) }}</span>
                @endif
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-white">{{ $establishment->trade_name ?: tenant()->name }}</p>
                    <p class="text-xs text-stone-500">Painel do estabelecimento</p>
                </div>
                <button type="button" class="ml-auto rounded-lg p-1.5 text-stone-400 hover:bg-white/10 lg:hidden" @click="menu = false" aria-label="Fechar menu">
                    <x-icon name="x" class="size-5" />
                </button>
            </div>

            <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 pb-4 scrollbar-none">
                @foreach ($nav as $item)
                    @if (isset($item['section']))
                        @if (! $item['can'] || $user->canAny((array) $item['can']))
                            <p class="px-3 pt-5 pb-1.5 text-[11px] font-semibold tracking-wider text-stone-500 uppercase">{{ $item['section'] }}</p>
                        @endif
                    @elseif (! $item['can'] || $user->can($item['can']))
                        <a href="{{ route($item['route']) }}" wire:navigate
                           @class([
                               'group flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition',
                               'bg-white/10 text-white' => $isActive($item),
                               'text-stone-400 hover:bg-white/5 hover:text-stone-100' => ! $isActive($item),
                           ])>
                            <x-icon :name="$item['icon']" @class(['size-[18px]', 'text-brand-400' => $isActive($item)]) />
                            {{ $item['label'] }}
                        </a>
                    @endif
                @endforeach
            </nav>

            <div class="border-t border-white/10 p-3">
                <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-3 rounded-xl px-2 py-2 hover:bg-white/5">
                    <span class="flex size-9 items-center justify-center rounded-full bg-brand-600/20 text-xs font-bold text-brand-300">{{ $user->initials() }}</span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-white">{{ $user->name }}</span>
                        <span class="block truncate text-xs text-stone-500">{{ $user->roles->pluck('name')->implode(', ') }}</span>
                    </span>
                </a>
                <form method="POST" action="{{ route('logout') }}" class="mt-1">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-stone-400 hover:bg-white/5 hover:text-white">
                        <x-icon name="logout" class="size-[18px]" /> Sair
                    </button>
                </form>
            </div>
        </aside>

        <div class="fixed inset-0 z-30 bg-stone-950/50 backdrop-blur-sm lg:hidden" x-show="menu" x-transition.opacity x-cloak @click="menu = false"></div>

        <div class="min-w-0 flex-1">
            {{-- Topo (celular): menu, nome e atalho para o cardápio --}}
            <header class="print:hidden sticky top-0 z-20 flex items-center gap-3 border-b border-stone-200 bg-white/90 px-4 py-3 backdrop-blur lg:hidden">
                <button type="button" class="btn-icon btn-ghost -ml-2" @click="menu = true" aria-label="Abrir menu">
                    <x-icon name="menu" class="size-6" />
                </button>
                <p class="truncate font-semibold">{{ $title ?? 'Início' }}</p>
                <a href="{{ url('/cardapio') }}" target="_blank" class="btn-icon btn-ghost ml-auto -mr-2" aria-label="Ver cardápio público">
                    <x-icon name="external" class="size-5" />
                </a>
            </header>

            <main @class(['mx-auto px-4 py-6 sm:px-6 lg:px-8 lg:py-8', 'max-w-7xl' => ! ($wide ?? false)])>
                @if (session('status'))
                    <div class="alert-success mb-6" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" x-transition>
                        <x-icon name="check" class="mt-0.5 size-4" />
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
