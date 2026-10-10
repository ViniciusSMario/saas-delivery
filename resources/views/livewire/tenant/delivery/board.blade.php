<div class="space-y-4 sm:space-y-6" wire:poll.15s>
    <x-ui.page-header title="Entregas" subtitle="Pegue os pedidos prontos, siga a rota e marque como entregue. Atualiza sozinho.">
        @can('settings.manage')
            <a href="{{ route('delivery.zones') }}" class="btn-secondary" wire:navigate><x-icon name="pin" class="size-4" /> Bairros e taxas</a>
        @endcan
    </x-ui.page-header>

    @error('status') <div class="alert-error"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $message }}</div> @enderror
    @error('courier') <div class="alert-error"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $message }}</div> @enderror
    @if ($notice)
        <div class="alert-success" wire:key="notice-{{ md5($notice) }}">
            <x-icon name="check" class="mt-0.5 size-4" />
            <span class="flex-1">{{ $notice }}</span>
            <button type="button" wire:click="$set('notice', null)" class="-my-1 -mr-1 p-1" aria-label="Fechar aviso"><x-icon name="x" class="size-4" /></button>
        </div>
    @endif

    {{-- Celular: duas abas grandes --}}
    <div class="segmented lg:hidden" role="tablist">
        <button type="button" role="tab" wire:click="setTab('ready')" @class(['segment', 'segment-active' => $tab === 'ready']) aria-selected="{{ $tab === 'ready' ? 'true' : 'false' }}">
            <x-icon name="bag" class="size-5 max-[379px]:hidden" /> Para pegar
            <span @class(['flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-xs font-bold', 'bg-emerald-600 text-white' => $ready->isNotEmpty(), 'bg-stone-300 text-stone-600' => $ready->isEmpty()])>{{ $ready->count() }}</span>
        </button>
        <button type="button" role="tab" wire:click="setTab('mine')" @class(['segment', 'segment-active' => $tab === 'mine']) aria-selected="{{ $tab === 'mine' ? 'true' : 'false' }}">
            <x-icon name="truck" class="size-5 max-[379px]:hidden" /> Comigo
            <span @class(['flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-xs font-bold', 'bg-brand-600 text-white' => $mine->isNotEmpty(), 'bg-stone-300 text-stone-600' => $mine->isEmpty()])>{{ $mine->count() }}</span>
        </button>
    </div>

    {{-- Comigo --}}
    <section @class(['space-y-3', 'hidden' => $tab !== 'mine', 'lg:block' => $mine->isNotEmpty(), 'lg:hidden' => $mine->isEmpty()])>
        <h2 class="section-title hidden items-center gap-2 lg:flex"><x-icon name="truck" class="size-5 text-brand-600" /> Comigo agora ({{ $mine->count() }})</h2>
        @if ($mine->isEmpty())
            <div class="card">
                <x-ui.empty icon="truck" title="Nenhuma entrega com você" text="Pegue um pedido em “Para pegar”." />
            </div>
        @else
            @if ($route)
                <a href="{{ $route }}" target="_blank" rel="noopener" class="btn-primary btn-xl lg:w-auto">
                    <x-icon name="pin" class="size-5" /> {{ $mine->count() > 1 ? 'Rota com as '.$mine->count().' paradas' : 'Abrir rota no mapa' }}
                </a>
            @endif
            <div class="grid gap-3 lg:grid-cols-2">
                @foreach ($mine as $order)
                    @include('livewire.tenant.delivery.partials.card', ['order' => $order, 'mode' => 'mine'])
                @endforeach
            </div>
        @endif
    </section>

    {{-- Prontos para sair --}}
    <section @class(['space-y-3', 'hidden lg:block' => $tab !== 'ready'])>
        <h2 class="section-title hidden items-center gap-2 lg:flex"><x-icon name="bag" class="size-5 text-emerald-600" /> Prontos para sair ({{ $ready->count() }})</h2>
        @if ($ready->isEmpty())
            <div class="card">
                <x-ui.empty icon="bag" title="Nenhum pedido pronto agora" :text="$preparing->isNotEmpty() ? $preparing->count().' entrega(s) em preparo na cozinha. O celular vibra quando ficar pronto.' : 'Os pedidos de entrega aparecem aqui quando a cozinha marca como pronto.'" />
            </div>
        @else
            <div class="grid gap-3 lg:grid-cols-2">
                @foreach ($ready as $order)
                    @include('livewire.tenant.delivery.partials.card', ['order' => $order, 'mode' => 'ready'])
                @endforeach
            </div>
            @if ($preparing->isNotEmpty())
                <p class="text-sm text-stone-500">Em preparo: {{ $preparing->map(fn ($o) => '#'.$o->number)->implode(', ') }}</p>
            @endif
        @endif
    </section>

    {{-- O dia do entregador (a gerência vê o fechamento de todos lá embaixo) --}}
    @if (! $manager)
        <div class="flex items-center gap-3 rounded-2xl border border-stone-200 bg-white px-4 py-3">
            <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><x-icon name="check" class="size-5" stroke="2.4" /></span>
            <p class="flex-1 text-sm">
                <span class="block font-semibold">Hoje você entregou {{ $myDay['count'] }} {{ $myDay['count'] === 1 ? 'pedido' : 'pedidos' }}</span>
                <span class="text-stone-500">{{ $myDay['fees']->format() }} em taxas de entrega</span>
            </p>
        </div>
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
                    <thead><tr><th>Entregador</th><th class="text-right">Entregas</th><th class="text-right">Taxas</th></tr></thead>
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
