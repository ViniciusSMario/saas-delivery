@php
    use App\Modules\Orders\Enums\Fulfillment;

    $nextLabel = fn ($order) => match ($order->status->next($order->fulfillment)?->value) {
        'preparing' => $order->source->value === 'online' ? 'Aceitar e preparar' : 'Iniciar preparo',
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
         selected: null,
         {{-- O som é o mesmo do alarme de pedido online (resources/js/alarm.js): ligado por aparelho. --}}
         syncSound() {
             this.sound = Boolean(window.orderAlarm?.soundEnabled() && ! window.orderAlarm.isBlocked());
         },
         toggleSound() {
             const alarm = window.orderAlarm;
             if (! alarm) return;
             if (this.sound) {
                 alarm.toggleSound();
             } else {
                 if (! alarm.soundEnabled()) alarm.toggleSound();
                 alarm.unlock().then(() => { this.syncSound(); alarm.beep(); });
             }
             this.syncSound();
         },
         toggleFull() {
             if (document.fullscreenElement) document.exitFullscreen(); else this.$refs.board.requestFullscreen?.();
         },
         {{-- Teclado: setas escolhem o pedido (segue o pedido quando ele muda de coluna); Enter avança. --}}
         move(key) {
             const cols = [...this.$root.querySelectorAll('[data-column]')].map((c) => [...c.querySelectorAll('[data-order-card]')]);
             let col = -1, row = -1;
             cols.forEach((list, i) => { const j = list.findIndex((el) => Number(el.dataset.orderCard) === this.selected); if (j >= 0) { col = i; row = j; } });
             if (col < 0) {
                 col = cols.findIndex((list) => list.length);
                 if (col < 0) return;
                 row = 0;
             } else if (key === 'ArrowDown') {
                 row = Math.min(row + 1, cols[col].length - 1);
             } else if (key === 'ArrowUp') {
                 row = Math.max(row - 1, 0);
             } else {
                 const step = key === 'ArrowRight' ? 1 : -1;
                 let next = col + step;
                 while (next >= 0 && next < cols.length && ! cols[next].length) next += step;
                 if (next < 0 || next >= cols.length) return;
                 col = next;
                 row = Math.min(row, cols[col].length - 1);
             }
             const card = cols[col][row];
             this.selected = Number(card.dataset.orderCard);
             card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
         },
         act(name) {
             this.$root.querySelector(`[data-order-card='${this.selected}'] [data-act='${name}']`)?.click();
         },
         onKey(e) {
             if (e.defaultPrevented || e.ctrlKey || e.altKey || e.metaKey || e.target.closest('input, textarea, select') || document.querySelector('[role=alertdialog], [data-hotkeys-modal]')) return;
             const key = e.key;
             if (key.startsWith('Arrow')) { e.preventDefault(); this.move(key); return; }
             if (this.selected === null) return;
             const action = { Enter: 'advance', i: 'reprint', I: 'reprint', x: 'cancel', X: 'cancel' }[key];
             if (! action) return;
             e.preventDefault();
             this.act(action);
         },
     }"
     x-init="syncSound()"
     @order-alarm-changed.document="syncSound()"
     @fullscreenchange.document="full = !! document.fullscreenElement"
     @new-order.window="window.orderAlarm?.beep()"
     @keydown.window="onKey($event)">
    <template data-hotkey-help="arrowup arrowdown arrowleft arrowright">Escolher um pedido</template>
    <template data-hotkey-help="enter">Avançar o pedido escolhido</template>
    <template data-hotkey-help="i">Reimprimir o pedido escolhido</template>
    <template data-hotkey-help="x">Cancelar o pedido escolhido</template>

    <x-ui.page-header title="Painel de pedidos">
        <x-slot:subtitle>
            <span class="inline-flex items-center gap-1.5"><span class="size-2 animate-pulse rounded-full bg-emerald-500"></span> Ao vivo · atualiza a cada 10 s</span>
            <span class="text-stone-300">•</span> {{ $todayDone }} concluídos/cancelados hoje
        </x-slot:subtitle>

        @include('partials.shortcuts-button')
        <button type="button" class="btn-secondary" data-hotkey="s" data-hotkey-label="Ligar ou desligar o som" @click="toggleSound()" :class="sound && 'border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100'">
            <span x-show="sound"><x-icon name="volume" class="size-4" /></span>
            <span x-show="! sound"><x-icon name="volume-off" class="size-4" /></span>
            <span x-text="sound ? 'Som ligado' : 'Ligar som'">Ligar som</span>
        </button>
        <button type="button" class="btn-secondary" data-hotkey="f" data-hotkey-label="Tela cheia" @click="toggleFull()">
            <x-icon name="expand" class="size-4" /> <span x-text="full ? 'Sair da tela cheia' : 'Tela cheia'">Tela cheia</span>
        </button>
        <a href="{{ route('orders.index') }}" class="btn-secondary" wire:navigate x-show="! full"><x-icon name="clock" class="size-4" /> Histórico</a>
        @can('pos.use')
            <a href="{{ route('orders.counter') }}" class="btn-primary" wire:navigate x-show="! full" data-hotkey="n" data-hotkey-label="Nova venda no balcão"><x-icon name="plus" class="size-4" /> Venda no balcão</a>
        @endcan
    </x-ui.page-header>

    <livewire:tenant.orders.rush-control />

    <div class="grid items-start gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($columns as $column)
            @php($list = $orders->get($column->value, collect()))
            @php([$columnColor, $columnIcon] = $columnStyle[$column->value] ?? ['bg-stone-500', 'list'])
            <section class="flex min-h-40 flex-col rounded-2xl bg-stone-200/50 p-2.5" data-column>
                <h2 class="flex items-center gap-2 px-1.5 pt-1 pb-3 text-sm font-bold text-stone-800">
                    <span class="flex size-7 items-center justify-center rounded-lg text-white {{ $columnColor }}"><x-icon :name="$columnIcon" class="size-4" stroke="2.2" /></span>
                    {{ $column->label() }}
                    <span class="ml-auto rounded-full bg-white px-2.5 py-0.5 text-xs font-bold text-stone-700 shadow-xs">{{ $list->count() }}</span>
                </h2>

                <div class="space-y-2.5">
                    @forelse ($list as $order)
                        @php($minutes = (int) abs($order->created_at->diffInMinutes(now())))
                        @php([$badge, $badgeIcon] = $fulfillmentStyle[$order->fulfillment->value] ?? ['badge-gray', 'orders'])
                        <article wire:key="order-{{ $order->id }}" data-order-card="{{ $order->id }}" @click="selected = {{ $order->id }}"
                                 :class="selected === {{ $order->id }} && 'ring-2 ring-brand-500 ring-offset-2 ring-offset-stone-100'" @class([
                            'scroll-m-4 overflow-hidden rounded-xl border-l-4 bg-white text-sm shadow-(--shadow-card) transition-shadow',
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
                                    @if ($order->via_qr)<span class="badge-brand"><x-icon name="qr" class="size-3.5" /> pelo celular</span>@endif
                                    <span class="truncate text-xs font-medium text-stone-600">{{ $order->customer_name }}</span>
                                    @if ($order->courier_id)<span class="badge-violet"><x-icon name="truck" class="size-3.5" /> {{ $order->courier?->name }}</span>@endif
                                    @if ($order->address_district && $order->fulfillment->value === 'delivery')<span class="text-xs text-stone-500">· {{ $order->address_district }}</span>@endif
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
                                    <button type="button" wire:click="reprint({{ $order->id }})" data-act="reprint" class="btn-icon btn-ghost ml-auto size-8" title="Reimprimir nos setores" aria-label="Reimprimir pedido {{ $order->number }}">
                                        <x-icon name="printer" class="size-4" />
                                    </button>
                                    @can('orders.cancel')
                                        <button type="button" wire:click="startCancel({{ $order->id }})" data-act="cancel" class="btn-ghost btn-sm -mr-2 text-red-600 hover:bg-red-50 hover:text-red-700" title="Cancelar pedido">Cancelar</button>
                                    @endcan
                                </div>
                                @can('orders.manage')
                                    @if ($label = $nextLabel($order))
                                        <button type="button" wire:click="advance({{ $order->id }})" data-act="advance" class="btn-primary w-full">
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
