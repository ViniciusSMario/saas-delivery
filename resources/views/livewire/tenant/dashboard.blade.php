<div class="space-y-6">
    <div>
        <h1 class="page-title">Olá, {{ str(auth()->user()->name)->before(' ') }}!</h1>
        <p class="mt-1 text-sm text-stone-500">{{ $establishment->trade_name }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card card-body">
            <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Hoje</p>
            @if (! $today || $today->is_closed)
                <p class="mt-2 text-lg font-semibold">Fechado</p>
            @else
                <p class="mt-2 text-lg font-semibold">{{ substr($today->opens_at, 0, 5) }} às {{ substr($today->closes_at, 0, 5) }}</p>
            @endif
            @can('establishment.manage')
                <a href="{{ route('establishment.edit') }}" class="btn-link mt-2 inline-block" wire:navigate>Alterar horários</a>
            @endcan
        </div>

        <div class="card card-body">
            <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Usuários ativos</p>
            <p class="mt-2 text-lg font-semibold">{{ $activeUsers }}</p>
            @can('users.view')
                <a href="{{ route('users.index') }}" class="btn-link mt-2 inline-block" wire:navigate>Ver equipe</a>
            @endcan
        </div>

        <div class="card card-body">
            <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Cardápio público</p>
            <a href="{{ url('/cardapio') }}" target="_blank" class="mt-2 block truncate text-sm font-medium text-brand-700 hover:underline">{{ request()->getHttpHost() }}/cardapio</a>
            <p class="hint">Os produtos chegam na próxima fase.</p>
        </div>
    </div>

    <div class="card card-body">
        <h2 class="font-semibold">Seu acesso</h2>
        <p class="mt-1 text-sm text-stone-500">Perfis: {{ auth()->user()->roles->pluck('name')->implode(', ') ?: 'nenhum' }}</p>
        @if (! auth()->user()->roles->count())
            <p class="hint">Peça ao dono do estabelecimento para atribuir um perfil ao seu usuário.</p>
        @endif
    </div>
</div>
