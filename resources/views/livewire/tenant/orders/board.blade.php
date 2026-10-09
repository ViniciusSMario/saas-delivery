@php
    use App\Modules\Orders\Enums\Fulfillment;

    $nextLabel = fn ($order) => match ($order->status->next($order->fulfillment)?->value) {
        'preparing' => 'Iniciar preparo',
        'ready' => 'Marcar pronto',
        'dispatched' => 'Saiu para entrega',
        'completed' => match ($order->fulfillment) { Fulfillment::Delivery => 'Entregue', Fulfillment::Table => 'Servido', default => 'Concluir' },
        default => null,
    };
    $fulfillmentStyle = ['delivery' => 'bg-sky-100 text-sky-800', 'pickup' => 'bg-violet-100 text-violet-800', 'counter' => 'bg-stone-200 text-stone-700', 'table' => 'bg-emerald-100 text-emerald-800'];
@endphp

<div class="space-y-4" wire:poll.10s
     x-data="{
         sound: false,
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
     }"
     @new-order.window="beep(); flash($event.detail.count)">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="page-title">Painel de pedidos</h1>
            <p class="mt-1 text-sm text-stone-500">Atualiza sozinho a cada 10 segundos · {{ $todayDone }} concluídos/cancelados hoje</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn-secondary" @click="sound = ! sound; beep()" x-text="sound ? '🔔 Som ligado' : '🔕 Ligar som'"></button>
            <a href="{{ route('orders.index') }}" class="btn-secondary" wire:navigate>Histórico</a>
            @can('pos.use')
                <a href="{{ route('orders.counter') }}" class="btn-primary" wire:navigate>Venda no balcão</a>
            @endcan
        </div>
    </div>

    @error('board') <div class="alert-error">{{ $message }}</div> @enderror

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($columns as $column)
            @php($list = $orders->get($column->value, collect()))
            <section class="rounded-xl bg-stone-200/60 p-3">
                <h2 class="flex items-center justify-between px-1 text-sm font-semibold text-stone-700">
                    {{ $column->label() }}
                    <span class="badge bg-white text-stone-700">{{ $list->count() }}</span>
                </h2>

                <div class="mt-3 space-y-3">
                    @forelse ($list as $order)
                        <article wire:key="order-{{ $order->id }}" class="card p-3 text-sm">
                            <div class="flex items-start justify-between gap-2">
                                <a href="{{ route('orders.show', $order) }}" class="text-base font-bold hover:underline" wire:navigate>#{{ $order->number }}</a>
                                <span class="text-xs text-stone-500" title="{{ $order->created_at->timezone('America/Sao_Paulo')->format('d/m H:i') }}">{{ $order->created_at->locale('pt_BR')->diffForHumans(short: true) }}</span>
                            </div>
                            <p class="mt-1 flex flex-wrap items-center gap-1">
                                <span class="badge {{ $fulfillmentStyle[$order->fulfillment->value] }}">{{ $order->fulfillment->label() }}</span>
                                @if ($order->source->value === 'online')<span class="badge bg-brand-100 text-brand-700">online</span>@endif
                                <span class="truncate text-stone-600">{{ $order->customer_name }}</span>
                            </p>

                            <ul class="mt-2 space-y-0.5">
                                @foreach ($order->items as $item)
                                    <li>
                                        <span class="font-medium">{{ $item->quantity }}×</span> {{ $item->name }}
                                        @if ($item->addons->isNotEmpty())<span class="block pl-5 text-xs text-stone-500">{{ $item->addons->pluck('name')->implode(', ') }}</span>@endif
                                        @if ($item->notes)<span class="block pl-5 text-xs font-medium text-amber-700">Obs.: {{ $item->notes }}</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                            @if ($order->notes)
                                <p class="mt-2 rounded bg-amber-50 px-2 py-1 text-xs text-amber-800">{{ $order->notes }}</p>
                            @endif

                            <div class="mt-3 flex items-center justify-between gap-2">
                                <span class="font-semibold">{{ $order->total_cents->format() }}</span>
                                <div class="flex gap-1">
                                    @can('orders.cancel')
                                        <button type="button" wire:click="startCancel({{ $order->id }})" class="btn-secondary px-2 py-1 text-xs text-red-700">Cancelar</button>
                                    @endcan
                                    @can('orders.manage')
                                        @if ($label = $nextLabel($order))
                                            <button type="button" wire:click="advance({{ $order->id }})" wire:loading.attr="disabled" class="btn-primary px-2 py-1 text-xs">{{ $label }}</button>
                                        @endif
                                    @endcan
                                </div>
                            </div>

                            @if ($cancellingId === $order->id)
                                <form wire:submit="cancel" class="mt-3 space-y-2 border-t border-stone-100 pt-3">
                                    <input wire:model="cancelReason" type="text" class="input" placeholder="Motivo do cancelamento" maxlength="255" autofocus>
                                    @error('cancelReason') <p class="field-error">{{ $message }}</p> @enderror
                                    <div class="flex justify-end gap-2">
                                        <button type="button" wire:click="$set('cancellingId', null)" class="btn-secondary px-2 py-1 text-xs">Voltar</button>
                                        <button type="submit" class="btn-danger px-2 py-1 text-xs">Confirmar cancelamento</button>
                                    </div>
                                </form>
                            @endif
                        </article>
                    @empty
                        <p class="px-1 py-6 text-center text-xs text-stone-500">Nenhum pedido.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</div>
