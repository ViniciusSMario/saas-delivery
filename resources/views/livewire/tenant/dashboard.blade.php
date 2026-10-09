@php
    $variation = function ($current, $previous) {
        if (! $previous) {
            return null;
        }

        return round(($current - $previous) / $previous * 100);
    };
@endphp
<div class="space-y-6" wire:poll.60s>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Olá, {{ str(auth()->user()->name)->before(' ') }}!</h1>
            <p class="mt-1 text-sm text-stone-500">
                {{ $establishment->trade_name }} · {{ $now->locale('pt_BR')->isoFormat('dddd, D [de] MMMM') }} ·
                @if (! $today || $today->is_closed) fechado hoje @else {{ substr($today->opens_at, 0, 5) }} às {{ substr($today->closes_at, 0, 5) }} @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('pos.use')<a href="{{ route('orders.counter') }}" class="btn-secondary" wire:navigate>PDV</a>@endcan
            @can('tables.manage')<a href="{{ route('tables.map') }}" class="btn-secondary" wire:navigate>Mesas</a>@endcan
            @can('orders.view')<a href="{{ route('orders.board') }}" class="btn-primary" wire:navigate>Painel de pedidos</a>@endcan
        </div>
    </div>

    @if ($lowStock->isNotEmpty())
        <a href="{{ route('stock.index', ['onlyLow' => 1]) }}" wire:navigate class="alert-error block">
            <span class="font-semibold">Estoque baixo:</span>
            {{ $lowStock->map(fn ($i) => $i->name.' ('.\App\Support\Quantity::format($i->quantity).' '.$i->unit->code.')')->implode(', ') }}
        </a>
    @endif

    @isset($overdue)
        @unless ($overdue->isZero() && $dueToday->isZero())
            <a href="{{ route('finance.index', ['status' => 'overdue']) }}" wire:navigate class="alert-error block">
                <span class="font-semibold">Contas a pagar:</span>
                @unless ($dueToday->isZero()) {{ $dueToday->format() }} vencem hoje @endunless
                @unless ($overdue->isZero()) {{ $dueToday->isZero() ? '' : '·' }} {{ $overdue->format() }} vencidas @endunless
            </a>
        @endunless
    @endisset

    @if ($sales)
        @php
            $revenueVar = $variation($sales['revenue']->cents, $lastWeek['revenue']->cents);
            $countVar = $variation($sales['count'], $lastWeek['count']);
        @endphp
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card card-body">
                <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Vendas hoje</p>
                <p class="mt-1 text-2xl font-semibold">{{ $sales['revenue']->format() }}</p>
                @if ($revenueVar !== null)
                    <p @class(['text-xs', 'text-green-700' => $revenueVar >= 0, 'text-red-700' => $revenueVar < 0])>{{ $revenueVar >= 0 ? '+' : '' }}{{ $revenueVar }}% vs. semana passada</p>
                @else
                    <p class="hint">Semana passada: {{ $lastWeek['revenue']->format() }}</p>
                @endif
            </div>
            <div class="card card-body">
                <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Pedidos / contas</p>
                <p class="mt-1 text-2xl font-semibold">{{ $sales['count'] }}</p>
                @if ($countVar !== null)
                    <p @class(['text-xs', 'text-green-700' => $countVar >= 0, 'text-red-700' => $countVar < 0])>{{ $countVar >= 0 ? '+' : '' }}{{ $countVar }}% vs. semana passada</p>
                @endif
            </div>
            <div class="card card-body">
                <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Ticket médio</p>
                <p class="mt-1 text-2xl font-semibold">{{ $sales['average']->format() }}</p>
                <p class="hint">Semana passada: {{ $lastWeek['average']->format() }}</p>
            </div>
            <div class="card card-body">
                <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Agora</p>
                <p class="mt-1 text-sm">
                    @isset($openOrders)<span class="block"><strong>{{ $openOrders }}</strong> pedido(s) em andamento</span>@endisset
                    @isset($openTables)<span class="block"><strong>{{ $openTables }}</strong> mesa(s) ocupada(s)</span>@endisset
                </p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card card-body">
                <h2 class="font-semibold">Por canal</h2>
                <dl class="mt-2 space-y-1 text-sm">
                    @foreach ($sales['channels'] as $channel)
                        <div class="flex justify-between"><dt>{{ $channel['label'] }} ({{ $channel['count'] }})</dt><dd class="font-medium">{{ $channel['total']->format() }}</dd></div>
                    @endforeach
                </dl>
            </section>
            <section class="card card-body">
                <h2 class="font-semibold">Recebido hoje</h2>
                <dl class="mt-2 space-y-1 text-sm">
                    @forelse ($methods as $row)
                        <div class="flex justify-between"><dt>{{ $row['method'] }}</dt><dd class="font-medium">{{ $row['total']->format() }}</dd></div>
                    @empty
                        <p class="text-stone-500">Nada recebido no caixa ainda.</p>
                    @endforelse
                </dl>
                <a href="{{ route('reports.index') }}" class="btn-link mt-3 inline-block text-sm" wire:navigate>Ver relatórios</a>
            </section>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @isset($openOrders)
                <a href="{{ route('orders.board') }}" wire:navigate class="card card-body hover:border-brand-500">
                    <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Pedidos em andamento</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $openOrders }}</p>
                </a>
            @endisset
            @isset($openTables)
                <a href="{{ route('tables.map') }}" wire:navigate class="card card-body hover:border-brand-500">
                    <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Mesas ocupadas</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $openTables }}</p>
                </a>
            @endisset
        </div>
        <p class="text-sm text-stone-500">Seus perfis: {{ auth()->user()->roles->pluck('name')->implode(', ') ?: 'nenhum (peça ao dono para atribuir um)' }}.</p>
    @endif

    <p class="text-xs text-stone-400">Cardápio público: <a href="{{ url('/cardapio') }}" target="_blank" class="hover:underline">{{ request()->getHttpHost() }}/cardapio</a></p>
</div>
