@php
    $variation = function ($current, $previous) {
        if (! $previous) {
            return null;
        }

        return round(($current - $previous) / $previous * 100);
    };
    $shortcuts = [
        ['can' => 'orders.view', 'route' => 'orders.board', 'label' => 'Painel de pedidos', 'text' => 'Cozinha e expedição', 'icon' => 'flame', 'tone' => 'bg-brand-50 text-brand-600'],
        ['can' => 'pos.use', 'route' => 'orders.counter', 'label' => 'PDV', 'text' => 'Venda no balcão', 'icon' => 'pos', 'tone' => 'bg-sky-50 text-sky-600'],
        ['can' => 'tables.manage', 'route' => 'tables.map', 'label' => 'Mesas', 'text' => 'Comandas e contas', 'icon' => 'table', 'tone' => 'bg-violet-50 text-violet-600'],
        ['can' => 'cash.operate', 'route' => 'cash.current', 'label' => 'Caixa', 'text' => 'Abertura e sangrias', 'icon' => 'wallet', 'tone' => 'bg-emerald-50 text-emerald-600'],
    ];
@endphp
<div class="space-y-6" wire:poll.60s>
    <x-ui.page-header :title="'Olá, '.str(auth()->user()->name)->before(' ').'!'">
        <a href="{{ url('/cardapio') }}" target="_blank" class="btn-secondary">
            <x-icon name="external" class="size-4" /> Ver cardápio
        </a>
        @can('orders.view')
            <a href="{{ route('orders.board') }}" class="btn-primary" wire:navigate>
                <x-icon name="flame" class="size-4" /> Painel de pedidos
            </a>
        @endcan
    </x-ui.page-header>

    <p class="-mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-stone-500">
        <span>{{ $establishment->trade_name }}</span>
        <span class="text-stone-300">•</span>
        <span>{{ ucfirst($now->locale('pt_BR')->isoFormat('dddd, D [de] MMMM')) }}</span>
        <span class="text-stone-300">•</span>
        @if (! $today || $today->is_closed)
            <span class="badge-gray">Fechado hoje</span>
        @else
            <span class="badge-green">{{ substr($today->opens_at, 0, 5) }} às {{ substr($today->closes_at, 0, 5) }}</span>
        @endif
    </p>

    {{-- Primeiros passos: até concluir ou esconder --}}
    @if ($onboarding)
        @php
            $pending = collect($onboarding['steps'])->first(fn ($s) => ! $s['done'] && ! $s['skipped']);
        @endphp
        <section class="card overflow-hidden" aria-label="Primeiros passos">
            <div class="flex flex-wrap items-center gap-4 border-b border-stone-100 px-5 py-4">
                <div class="min-w-0 flex-1">
                    <h2 class="font-bold">Primeiros passos: {{ $onboarding['progress']['done'] }} de {{ $onboarding['progress']['total'] }}</h2>
                    <div class="mt-2 h-2 max-w-md overflow-hidden rounded-full bg-stone-200"><div class="h-full rounded-full bg-brand-600" style="width: {{ $onboarding['progress']['percent'] }}%"></div></div>
                </div>
                <a href="{{ route('onboarding', $pending ? ['step' => $pending['key']] : []) }}" class="btn-primary" wire:navigate>
                    {{ $pending ? 'Continuar: '.$pending['title'] : 'Revisar e concluir' }} <x-icon name="chevron-right" class="size-4" />
                </a>
                <button type="button" wire:click="dismissOnboarding" class="btn-ghost btn-sm text-stone-500"
                        wire:confirm="Esconder os primeiros passos? Dá para configurar tudo pelas telas do menu quando quiser." data-confirm-button="Esconder" data-confirm-tone="default">Esconder</button>
            </div>
            <ul class="grid gap-x-6 gap-y-1 px-5 py-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($onboarding['steps'] as $item)
                    <li>
                        <a href="{{ route('onboarding', ['step' => $item['key']]) }}" wire:navigate class="flex items-center gap-2 py-1 text-sm hover:underline">
                            @if ($item['done'])
                                <x-icon name="check" class="size-4 text-emerald-600" stroke="3" /> <span class="text-stone-500 line-through">{{ $item['title'] }}</span>
                            @elseif ($item['skipped'])
                                <x-icon name="minus" class="size-4 text-stone-400" /> <span class="text-stone-400">{{ $item['title'] }} (pulado)</span>
                            @else
                                <span class="size-4 rounded-full border-2 border-stone-300"></span> <span class="font-medium">{{ $item['title'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Alertas --}}
    @if ($lowStock->isNotEmpty())
        <a href="{{ route('stock.index', ['onlyLow' => 1]) }}" wire:navigate class="alert-warning transition hover:border-amber-300">
            <x-icon name="alert" class="mt-0.5 size-4" />
            <span>
                <span class="font-semibold">Estoque baixo:</span>
                <span class="font-normal">{{ $lowStock->map(fn ($i) => $i->name.' ('.\App\Support\Quantity::format($i->quantity).' '.$i->unit->code.')')->implode(', ') }}</span>
            </span>
            <x-icon name="chevron-right" class="ml-auto size-4" />
        </a>
    @endif

    @isset($overdue)
        @unless ($overdue->isZero() && $dueToday->isZero())
            <a href="{{ route('finance.index', ['status' => 'overdue']) }}" wire:navigate class="alert-error transition hover:border-red-300">
                <x-icon name="calendar" class="mt-0.5 size-4" />
                <span>
                    <span class="font-semibold">Contas a pagar:</span>
                    <span class="font-normal">
                        @unless ($dueToday->isZero()) {{ $dueToday->format() }} vencem hoje @endunless
                        @unless ($overdue->isZero()) {{ $dueToday->isZero() ? '' : '·' }} {{ $overdue->format() }} vencidas @endunless
                    </span>
                </span>
                <x-icon name="chevron-right" class="ml-auto size-4" />
            </a>
        @endunless
    @endisset

    @if ($sales)
        @php
            $revenueVar = $variation($sales['revenue']->cents, $lastWeek['revenue']->cents);
            $countVar = $variation($sales['count'], $lastWeek['count']);
            $channelTotal = max(1, collect($sales['channels'])->sum(fn ($c) => $c['total']->cents));
            $methodTotal = max(1, collect($methods)->sum(fn ($m) => $m['total']->cents));
        @endphp
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat label="Vendas hoje" :value="$sales['revenue']->format()" icon="trending" tone="brand"
                       :trend="$revenueVar" :hint="$revenueVar !== null ? 'vs. semana passada' : 'Semana passada: '.$lastWeek['revenue']->format()" />
            <x-ui.stat label="Pedidos / contas" :value="$sales['count']" icon="orders" tone="blue" :trend="$countVar" :hint="$countVar !== null ? 'vs. semana passada' : 'Semana passada: '.$lastWeek['count']" />
            <x-ui.stat label="Ticket médio" :value="$sales['average']->format()" icon="cash" tone="green" :hint="'Semana passada: '.$lastWeek['average']->format()" />
            <div class="card card-body">
                <p class="stat-label">Agora</p>
                <div class="mt-3 space-y-2">
                    @isset($openOrders)
                        <a href="{{ route('orders.board') }}" wire:navigate class="flex items-center gap-3 rounded-xl bg-brand-50 px-3 py-2 hover:bg-brand-100">
                            <x-icon name="flame" class="size-5 text-brand-600" />
                            <span class="text-sm"><strong class="text-lg">{{ $openOrders }}</strong> pedido(s) em andamento</span>
                        </a>
                    @endisset
                    @isset($openTables)
                        <a href="{{ route('tables.map') }}" wire:navigate class="flex items-center gap-3 rounded-xl bg-violet-50 px-3 py-2 hover:bg-violet-100">
                            <x-icon name="table" class="size-5 text-violet-600" />
                            <span class="text-sm"><strong class="text-lg">{{ $openTables }}</strong> mesa(s) ocupada(s)</span>
                        </a>
                    @endisset
                </div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card">
                <div class="card-header"><h2 class="section-title">Vendas por canal</h2></div>
                <ul class="space-y-4 p-5 sm:p-6">
                    @foreach ($sales['channels'] as $channel)
                        <li>
                            <div class="flex justify-between text-sm">
                                <span class="font-medium">{{ $channel['label'] }} <span class="text-stone-400">· {{ $channel['count'] }}</span></span>
                                <span class="font-semibold tabular-nums">{{ $channel['total']->format() }}</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-stone-100">
                                <div class="h-full rounded-full bg-brand-500" style="width: {{ round($channel['total']->cents / $channelTotal * 100) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
            <section class="card">
                <div class="card-header">
                    <h2 class="section-title">Recebido hoje</h2>
                    @can('reports.view')<a href="{{ route('reports.index') }}" class="btn-link" wire:navigate>Ver relatórios</a>@endcan
                </div>
                @if (count($methods))
                    <ul class="space-y-4 p-5 sm:p-6">
                        @foreach ($methods as $row)
                            <li>
                                <div class="flex justify-between text-sm">
                                    <span class="font-medium">{{ $row['method'] }}</span>
                                    <span class="font-semibold tabular-nums">{{ $row['total']->format() }}</span>
                                </div>
                                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-stone-100">
                                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ round($row['total']->cents / $methodTotal * 100) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-ui.empty icon="wallet" title="Nada recebido ainda" text="Os pagamentos registrados no caixa hoje aparecem aqui." />
                @endif
            </section>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @isset($openOrders)
                <x-ui.stat label="Pedidos em andamento" :value="$openOrders" icon="flame" tone="brand" :href="route('orders.board')" />
            @endisset
            @isset($openTables)
                <x-ui.stat label="Mesas ocupadas" :value="$openTables" icon="table" tone="violet" :href="route('tables.map')" />
            @endisset
        </div>
        <p class="text-sm text-stone-500">Seus perfis: {{ auth()->user()->roles->pluck('name')->implode(', ') ?: 'nenhum (peça ao dono para atribuir um)' }}.</p>
    @endif

    {{-- Atalhos --}}
    @php($available = collect($shortcuts)->filter(fn ($s) => auth()->user()->can($s['can'])))
    @if ($available->isNotEmpty())
        <section>
            <h2 class="stat-label mb-3">Acesso rápido</h2>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($available as $shortcut)
                    <a href="{{ route($shortcut['route']) }}" wire:navigate class="card group flex items-center gap-3 p-4 transition hover:border-brand-300 hover:shadow-md">
                        <span class="flex size-11 items-center justify-center rounded-xl {{ $shortcut['tone'] }}"><x-icon :name="$shortcut['icon']" class="size-5" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold">{{ $shortcut['label'] }}</span>
                            <span class="block text-xs text-stone-500">{{ $shortcut['text'] }}</span>
                        </span>
                        <x-icon name="chevron-right" class="size-4 text-stone-300 transition group-hover:translate-x-0.5 group-hover:text-brand-500" />
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <p class="text-xs text-stone-400">Cardápio público: <a href="{{ url('/cardapio') }}" target="_blank" class="hover:underline">{{ request()->getHttpHost() }}/cardapio</a></p>
</div>
