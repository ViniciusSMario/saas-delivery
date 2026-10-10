<div class="space-y-6" wire:poll.15s>
    <x-ui.page-header title="Entregas" subtitle="Pedidos prontos para sair, rota e conclusão. Atualiza sozinho.">
        @can('settings.manage')
            <a href="{{ route('delivery.zones') }}" class="btn-secondary" wire:navigate><x-icon name="pin" class="size-4" /> Bairros e taxas</a>
        @endcan
    </x-ui.page-header>

    @error('status') <div class="alert-error"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $message }}</div> @enderror
    @error('courier') <div class="alert-error"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $message }}</div> @enderror

    {{-- Minhas entregas --}}
    @if ($mine->isNotEmpty())
        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="section-title flex items-center gap-2"><x-icon name="truck" class="size-5 text-brand-600" /> Comigo agora ({{ $mine->count() }})</h2>
                @if ($route)
                    <a href="{{ $route }}" target="_blank" rel="noopener" class="btn-primary"><x-icon name="pin" class="size-4" /> Rota com {{ $mine->count() > 1 ? 'todas as paradas' : 'a entrega' }}</a>
                @endif
            </div>
            <div class="grid gap-3 lg:grid-cols-2">
                @foreach ($mine as $order)
                    @include('livewire.tenant.delivery.partials.card', ['order' => $order, 'mode' => 'mine'])
                @endforeach
            </div>
        </section>
    @endif

    {{-- Prontos para sair --}}
    <section class="space-y-3">
        <h2 class="section-title flex items-center gap-2"><x-icon name="bag" class="size-5 text-emerald-600" /> Prontos para sair ({{ $ready->count() }})</h2>
        @if ($ready->isEmpty())
            <div class="card">
                <x-ui.empty icon="bag" title="Nenhum pedido pronto agora" :text="$preparing->isNotEmpty() ? $preparing->count().' entrega(s) em preparo na cozinha.' : 'Os pedidos de entrega aparecem aqui quando a cozinha marca como pronto.'" />
            </div>
        @else
            <div class="grid gap-3 lg:grid-cols-2">
                @foreach ($ready as $order)
                    @include('livewire.tenant.delivery.partials.card', ['order' => $order, 'mode' => 'ready'])
                @endforeach
            </div>
        @endif
    </section>

    @if ($preparing->isNotEmpty() && $ready->isNotEmpty())
        <p class="text-sm text-stone-500">Em preparo: {{ $preparing->map(fn ($o) => '#'.$o->number)->implode(', ') }}</p>
    @endif

    {{-- Gerência --}}
    @if ($manager)
        @if ($others->isNotEmpty())
            <section class="space-y-3">
                <h2 class="section-title">Na rua com outros entregadores ({{ $others->count() }})</h2>
                <div class="grid gap-3 lg:grid-cols-2">
                    @foreach ($others as $order)
                        @include('livewire.tenant.delivery.partials.card', ['order' => $order, 'mode' => 'other'])
                    @endforeach
                </div>
            </section>
        @endif

        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="section-title">Entregas concluídas hoje</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Entregador</th><th class="text-right">Entregas</th><th class="text-right">Taxas de entrega</th></tr></thead>
                    <tbody>
                        @forelse ($summary as $row)
                            <tr>
                                <td class="font-medium">{{ $row['courier'] }}</td>
                                <td class="text-right tabular-nums">{{ $row['count'] }}</td>
                                <td class="text-right font-semibold tabular-nums">{{ $row['fees']->format() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3"><x-ui.empty icon="truck" title="Nenhuma entrega concluída hoje" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
