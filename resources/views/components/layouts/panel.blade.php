@php
    $nav = \App\Support\PanelMenu::items();
    $user = auth()->user();
    $establishment = \App\Modules\Establishment\Models\Establishment::current();
    $tenant = tenant();
    $isOwner = $user->can('billing.manage');
    $locked = fn (array $item) => \App\Support\PanelMenu::locked($user, $item);
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
    @include('partials.pwa-head')
    @if ($user->canAny(['orders.view', 'orders.manage']))
        @vite('resources/js/alarm.js')
    @endif
</head>
<body class="min-h-screen" x-data="{ menu: false }" @keydown.escape.window="menu = false">
    <div class="lg:flex">
        {{-- Barra lateral --}}
        <aside data-sidebar class="print:hidden fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-stone-950 text-stone-300 transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:translate-x-0"
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

            <div class="px-3 pb-1">
                <button type="button" @click="menu = false; $dispatch('open-palette')" class="flex w-full items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-stone-400 transition hover:border-white/20 hover:text-stone-200">
                    <x-icon name="search" class="size-[18px]" />
                    <span class="flex-1 text-left">Buscar</span>
                    <span class="kbd">Ctrl K</span>
                </button>
            </div>

            <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 pb-4 scrollbar-none">
                @foreach ($nav as $item)
                    @if (isset($item['section']))
                        @if (! $item['can'] || $user->canAny((array) $item['can']) || $locked($item))
                            <p class="px-3 pt-5 pb-1.5 text-[11px] font-semibold tracking-wider text-stone-500 uppercase">{{ $item['section'] }}</p>
                        @endif
                    @elseif ($locked($item))
                        <a href="{{ route('subscription') }}" wire:navigate title="Disponível em outro plano" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-stone-600 hover:bg-white/5 hover:text-stone-300">
                            <x-icon :name="$item['icon']" class="size-[18px]" />
                            {{ $item['label'] }}
                            <x-icon name="lock" class="ml-auto size-3.5" />
                        </a>
                    @elseif (! $item['can'] || $user->canAny((array) $item['can']))
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
                <button type="button" class="btn-icon btn-ghost ml-auto" @click="$dispatch('open-palette')" aria-label="Buscar">
                    <x-icon name="search" class="size-5" />
                </button>
                <a href="{{ url('/cardapio') }}" target="_blank" class="btn-icon btn-ghost -mr-2" aria-label="Ver cardápio público">
                    <x-icon name="external" class="size-5" />
                </a>
            </header>

            {{-- Modo suporte: a equipe do SaaS está usando a conta de alguém daqui. --}}
            @if ($support = session('support_access'))
                <div class="print:hidden sticky top-0 z-30 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-violet-700 px-4 py-2 text-sm text-white">
                    <x-icon name="shield" class="size-4" />
                    <span>Modo suporte: <strong>{{ $support['email'] }}</strong> está usando a conta de {{ $support['user'] }}. Tudo fica registrado na auditoria.</span>
                    <form method="POST" action="{{ route('support.leave') }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-white/15 px-2.5 py-1 font-semibold hover:bg-white/25">Sair do modo suporte</button>
                    </form>
                </div>
            @endif

            <main @class(['mx-auto px-4 py-6 sm:px-6 lg:px-8 lg:py-8', 'max-w-7xl' => ! ($wide ?? false)])>
                {{-- Cadastro self-service: cardápio fora do ar até confirmar o e-mail. --}}
                @if ($isOwner && ! $tenant->email_verified_at)
                    <div class="print:hidden mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <x-icon name="alert" class="size-5 text-amber-600" />
                        <span class="flex-1">Confirme seu e-mail (<strong>{{ $tenant->billing_email }}</strong>) para colocar o cardápio online no ar. Enquanto isso, você já pode montar o cardápio.</span>
                        <form method="POST" action="{{ route('verification.resend') }}">
                            @csrf
                            <button type="submit" class="btn-secondary btn-sm">Reenviar e-mail</button>
                        </form>
                    </div>
                @endif

                {{-- Assinatura: aviso só para o dono --}}
                @if ($isOwner && ! request()->routeIs('subscription'))
                    @if ($tenant->subscription_status === 'trialing' && ! $tenant->billing_subscription_id)
                        <div class="print:hidden mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                            <x-icon name="clock" class="size-5 text-sky-600" />
                            <span class="flex-1">Teste grátis: faltam <strong>{{ $tenant->trialDaysLeft() }} dia(s)</strong>. Escolha um plano para continuar sem interrupção.</span>
                            <a href="{{ route('subscription') }}" wire:navigate class="btn-primary btn-sm">Escolher plano</a>
                        </div>
                    @elseif ($tenant->subscription_status === 'past_due')
                        <div class="print:hidden mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                            <x-icon name="alert" class="size-5 text-red-600" />
                            <span class="flex-1">Assinatura com pagamento pendente. Regularize para evitar a suspensão do acesso.</span>
                            @if ($payUrl = $tenant->openInvoiceUrl())
                                <a href="{{ $payUrl }}" target="_blank" class="btn-danger btn-sm">Pagar fatura</a>
                            @else
                                <a href="{{ route('subscription') }}" wire:navigate class="btn-danger btn-sm">Escolher plano</a>
                            @endif
                        </div>
                    @endif

                    {{-- Limite brando de pedidos do mês: só avisa, nunca bloqueia. --}}
                    @if (\App\Modules\Orders\MonthlyOrders::limit())
                        @php($ordersCount = \App\Modules\Orders\MonthlyOrders::count())
                        @php($ordersPercent = \App\Modules\Orders\MonthlyOrders::percent($ordersCount))
                        @if ($ordersPercent >= 80)
                            <div @class(['print:hidden mb-6 flex flex-wrap items-center gap-3 rounded-2xl border px-4 py-3 text-sm', 'border-red-200 bg-red-50 text-red-900' => $ordersPercent >= 100, 'border-amber-200 bg-amber-50 text-amber-900' => $ordersPercent < 100])>
                                <x-icon name="trending" class="size-5" />
                                <span class="flex-1">
                                    {{ $ordersCount }} de {{ \App\Modules\Orders\MonthlyOrders::limit() }} pedidos do mês ({{ $ordersPercent }}%).
                                    {{ $ordersPercent >= 100 ? 'Você passou do limite do plano; os pedidos continuam entrando.' : 'Você está perto do limite do plano.' }}
                                </span>
                                <a href="{{ route('subscription') }}" wire:navigate class="btn-secondary btn-sm">Ver planos</a>
                            </div>
                        @endif
                    @endif
                @endif

                @include('partials.toasts-flash')

                @if (! request()->routeIs('profile') && \App\Modules\Push\StaffAlerts::receivesAlerts($user))
                    @include('partials.push-banner', ['what' => 'quando chegar um pedido pelo cardápio online', 'class' => 'mb-6'])
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

    @include('partials.command-palette')

    {{-- Pedido online esperando aceite: cartão e som em qualquer tela do painel. --}}
    @can('orders.manage')
        <livewire:tenant.order-alarm />
    @endcan
</body>
</html>
