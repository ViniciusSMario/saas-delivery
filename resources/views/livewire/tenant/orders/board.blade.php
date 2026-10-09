@php
    use App\Modules\Orders\Enums\Fulfillment;

    $nextLabel = fn ($order) => match ($order->status->next($order->fulfillment)?->value) {
        'preparing' => 'Iniciar preparo',
        'ready' => 'Marcar pronto',
        'dispatched' => 'Saiu para entrega',
        'completed' => match ($order->fulfillment) { Fulfillment::Delivery => 'Entregue', Fulfillment::Table => 'Servido', default => 'Concluir' },
        default => null,
    };
    $fulfillmentStyle = [
        'delivery' => ['badge-blue', 'truck'],
        'pickup' => ['badge-violet', 'bag'],
        'counter' => ['badge-gray', 'pos'],
        'table' => ['badge-green', 'table'],
    ];
    $columnStyle = [
        'received' => ['bg-sky-500', 'bell'],
        'preparing' => ['bg-amber-500', 'flame'],
        'ready' => ['bg-emerald-500', 'check'],
        'dispatched' => ['bg-violet-500', 'truck'],
    ];
@endphp

<div class="space-y-5 bg-stone-100 [&:fullscreen]:overflow-y-auto [&:fullscreen]:p-6" wire:poll.10s x-ref="board"
     x-data="{
         sound: false,
         full: false,
         beep() {
             if (! this.sound) return;
             try {
                 const ctx = new (window.AudioContext || window.webkitAudioContext)();
                 [0, 0.25].forEach((t) => {
                     const o = ctx.createOscillator(), g = ctx.createGain();
                     o.frequency.value = 880; o.connect(g); g.connect(ctx.destination);
                     g.gain.setValueAtTime(0.2, ctx.currentTime + t);
                     o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.15);
                 });
             } catch (e) {}
         },
         flash(count) {
             const original = document.title.replace(/^\(\d+\) /, '');
             document.title = '(' + count + ') ' + original;
             setTimeout(() => document.title = original, 8000);
         },
         toggleFull() {
             if (document.fullscreenElement) document.exitFullscreen(); else this.$refs.board.requestFullscreen?.();
         },
     }"
     @fullscreenchange.document="full = !! document.fullscreenElement"
     @new-order.window="beep(); flash($event.detail.count)">

    <x-ui.page-header title="Painel de pedidos">
        <x-slot:subtitle>
            <span class="inline-flex items-center gap-1.5"><span class="size-2 animate-pulse rounded-full bg-emerald-500"></span> Ao vivo · atualiza a cada 10 s</span>
            <span class="text-stone-300">•</span> {{ $todayDone }} concluídos/cancelados hoje
        </x-slot:subtitle>

        <button type="button" class="btn-secondary" @click="sound = ! sound; beep()" :class="sound && 'border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100'">
            <span x-show="sound"><x-icon name="bell" class="size-4" /></span>
            <span x-show="! sound"><x-icon name="bell-off" class="size-4" /></span>
            <span x-text="sound ? 'Som ligado' : 'Ligar som'">Ligar som</span>
        </button>
        <button type="button" class="btn-secondary" @click="toggleFull()">
            <x-icon name="expand" class="size-4" /> <span x-text="full ? 'Sair da tela cheia' : 'Tela cheia'">Tela cheia</span>
        </button>
        <a href="{{ route('orders.index') }}" class="btn-secondary" wire:navigate x-show="! full"><x-icon name="clock" class="size-4" /> Histórico</a>
        @can('pos.use')
            <a href="{{ route('orders.counter') }}" class="btn-primary" wire:navigate x-show="! full"><x-icon name="plus" class="size-4" /> Venda no balcão</a>
        @endcan
    </x-ui.page-header>

    @error('board') <div class="alert-error"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $message }}</div> @enderror

    <div class="grid items-start gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($columns as $column)
            @php($list = $orders->get($column->value, collect()))
            @php([$columnColor, $columnIcon] = $columnStyle[$column->value] ?? ['bg-stone-500', 'list'])
            <section class="flex min-h-40 flex-col rounded-2xl bg-stone-200/50 p-2.5">
                <h2 class="flex items-center gap-2 px-1.5 pt-1 pb-3 text-sm font-bold text-stone-800">
                    <span class="flex size-7 items-center justify-center rounded-lg text-white {{ $columnColor }}"><x-icon :name="$columnIcon" class="size-4" stroke="2.2" /></span>
                    {{ $column->label() }}
                    <span class="ml-auto rounded-full bg-white px-2.5 py-0.5 text-xs font-bold text-stone-700 shadow-xs">{{ $list->count() }}</span>
                </h2>

                <div class="space-y-2.5">
                    @forelse ($list as $order)
                        @php($minutes = (int) abs($order->created_at->diffInMinutes(now())))
                        @php([$badge, $badgeIcon] = $fulfillmentStyle[$order->fulfillment->value] ?? ['badge-gray', 'orders'])
                        <article wire:key="order-{{ $order->id }}" @class([
                            'overflow-hidden rounded-xl border-l-4 bg-white text-sm shadow-(--shadow-card)',
                            'border-l-stone-300' => $minutes < 10,
                            'border-l-amber-400' => $minutes >= 10 && $minutes < 20,
                            'border-l-red-500' => $minutes >= 20,
                        ])>
                            <div class="p-3.5">
                                <div class="flex items-start justify-between gap-2">
                                    <a href="{{ route('orders.show', $order) }}" class="text-xl font-extrabold tracking-tight hover:text-brand-700" wire:navigate>#{{ $order->number }}</a>
                                    <span title="{{ $order->created_at->timezone('America/Sao_Paulo')->format('d/m H:i') }}" @class([
                                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-bold tabular-nums',
                                        'bg-stone-100 text-stone-600' => $minutes < 10,
                                        'bg-amber-100 text-amber-800' => $minutes >= 10 && $minutes < 20,
                                        'bg-red-100 text-red-700' => $minutes >= 20,
                                    ])>
                                        <x-icon name="clock" class="size-3.5" /> {{ $minutes < 60 ? $minutes.' min' : intdiv($minutes, 60).'h'.str_pad($minutes % 60, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                </div>
                                <p class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    <span class="{{ $badge }}"><x-icon :name="$badgeIcon" class="size-3.5" /> {{ $order->fulfillment->label() }}</span>
                                    @if ($order->source->value === 'online')<span class="badge-brand">online</span>@endif
                                    <span class="truncate text-xs font-medium text-stone-600">{{ $order->customer_name }}</span>
                                </p>

                                <ul class="mt-3 space-y-1.5 border-t border-dashed border-stone-200 pt-3">
                                    @foreach ($order->items as $item)
                                        <li class="flex gap-2">
                                            <span class="flex h-5 min-w-6 items-center justify-center rounded-md bg-stone-900 px-1 text-xs font-bold text-white">{{ $item->quantity }}</span>
                                            <span class="min-w-0">
                                                <span class="font-semibold text-stone-900">{{ $item->name }}</span>
                                                @if ($item->addons->isNotEmpty())<span class="block text-xs text-stone-500">{{ $item->addons->pluck('name')->implode(', ') }}</span>@endif
                                                @if ($item->notes)<span class="mt-0.5 block rounded bg-amber-50 px-1.5 py-0.5 text-xs font-semibold text-amber-800">Obs.: {{ $item->notes }}</span>@endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                                @if ($order->notes)
                                    <p class="mt-3 flex items-start gap-1.5 rounded-lg bg-amber-50 px-2.5 py-2 text-xs font-medium text-amber-900">
                                        <x-icon name="alert" class="size-3.5 shrink-0" /> {{ $order->notes }}
                                    </p>
                                @endif
                            </div>

                            {{-- Rodapé em duas linhas: total + cancelar, e a ação principal na largura toda
                                 (cabe em colunas estreitas, como 4 colunas em telas de 1366 px). --}}
                            <div class="space-y-2 border-t border-stone-100 bg-stone-50/70 px-3.5 py-2.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold whitespace-nowrap tabular-nums">{{ $order->total_cents->format() }}</span>
                                    @can('orders.cancel')
                                        <button type="button" wire:click="startCancel({{ $order->id }})" class="btn-ghost btn-sm -mr-2 text-red-600 hover:bg-red-50 hover:text-red-700" title="Cancelar pedido">Cancelar</button>
                                    @endcan
                                </div>
                                @can('orders.manage')
                                    @if ($label = $nextLabel($order))
                                        <button type="button" wire:click="advance({{ $order->id }})" wire:loading.attr="disabled" class="btn-primary w-full">
                                            {{ $label }} <x-icon name="chevron-right" class="size-4" />
                                        </button>
                                    @endif
                                @endcan
                            </div>

                            @if ($cancellingId === $order->id)
                                <form wire:submit="cancel" class="space-y-2 border-t border-red-100 bg-red-50/50 p-3.5">
                                    <input wire:model="cancelReason" type="text" class="input" placeholder="Motivo do cancelamento" maxlength="255" autofocus>
                                    @error('cancelReason') <p class="field-error">{{ $message }}</p> @enderror
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button type="button" wire:click="$set('cancellingId', null)" class="btn-secondary btn-sm">Voltar</button>
                                        <button type="submit" class="btn-danger btn-sm">Confirmar</button>
                                    </div>
                                </form>
                            @endif
                        </article>
                    @empty
                        <div class="flex flex-col items-center gap-1 rounded-xl border-2 border-dashed border-stone-300/70 px-3 py-8 text-center text-xs text-stone-500">
                            <x-icon :name="$columnIcon" class="size-5 text-stone-400" />
                            Nenhum pedido
                        </div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</div>
