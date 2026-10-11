<div wire:poll.15s.keep-alive x-data="orderAlarm" @order-alarm.window="update($event.detail)" class="print:hidden">
    @if ($count && ! $onBoard)
        <div x-data="{ expanded: false }" class="fixed inset-x-3 bottom-3 z-40 sm:inset-x-auto sm:right-6 sm:bottom-6 sm:w-96">
            {{-- Silenciado: vira uma pílula, que abre o cartão de novo. --}}
            <button type="button" x-show="silenced && ! expanded" x-cloak @click="expanded = true"
                    class="ml-auto flex items-center gap-2 rounded-full bg-brand-600 px-4 py-2.5 text-sm font-bold text-white shadow-float">
                <x-icon name="bell" class="size-4" />
                {{ $count === 1 ? '1 pedido esperando aceite' : "{$count} pedidos esperando aceite" }}
            </button>

            <section x-show="! silenced || expanded" role="alert" aria-live="assertive"
                     class="overflow-hidden rounded-2xl bg-white shadow-float ring-1 ring-brand-200">
                <header class="flex items-center gap-3 bg-brand-600 px-4 py-3 text-white">
                    <span class="relative flex size-9 shrink-0 items-center justify-center rounded-xl bg-white/15">
                        <span x-show="! silenced" class="absolute inset-0 animate-ping rounded-xl bg-white/25"></span>
                        <x-icon name="bell" class="size-5" stroke="2.2" />
                    </span>
                    <p class="min-w-0 flex-1 font-bold leading-tight">
                        {{ $count === 1 ? 'Pedido novo esperando aceite' : "{$count} pedidos novos esperando aceite" }}
                    </p>
                    <button type="button" @click="toggleSound()" class="shrink-0 rounded-lg p-1.5 hover:bg-white/15"
                            :aria-label="sound ? 'Desligar o som neste aparelho' : 'Ligar o som neste aparelho'" :title="sound ? 'Som ligado' : 'Som desligado'">
                        <span x-show="sound"><x-icon name="volume" class="size-5" /></span>
                        <span x-show="! sound" x-cloak><x-icon name="volume-off" class="size-5" /></span>
                    </button>
                </header>

                <button type="button" x-show="sound && blocked" x-cloak @click="unlockSound()"
                        class="flex w-full items-center gap-2 bg-amber-50 px-4 py-2 text-left text-xs font-semibold text-amber-900 hover:bg-amber-100">
                    <x-icon name="alert" class="size-4 shrink-0" /> O navegador bloqueou o som. Toque aqui para ligar.
                </button>

                <ul class="divide-y divide-stone-100">
                    @foreach ($orders as $order)
                        @php($minutes = (int) abs($order->created_at->diffInMinutes(now())))
                        <li wire:key="alarm-{{ $order->id }}" class="flex items-center gap-3 px-4 py-3">
                            <a href="{{ route('orders.show', $order) }}" wire:navigate class="min-w-0 flex-1">
                                <span class="flex items-baseline gap-2">
                                    <span class="text-lg font-extrabold tracking-tight">#{{ $order->number }}</span>
                                    <span class="font-semibold tabular-nums">{{ $order->total_cents->format() }}</span>
                                    <span @class(['ml-auto text-xs font-bold tabular-nums', 'text-stone-500' => $minutes < 5, 'text-red-600' => $minutes >= 5])>
                                        {{ $minutes < 1 ? 'agora' : "há {$minutes} min" }}
                                    </span>
                                </span>
                                <span class="block truncate text-xs text-stone-500">
                                    {{ $order->fulfillment->label() }}{{ $order->fulfillment->value === 'delivery' && $order->address_district ? ' · '.$order->address_district : '' }} · {{ $order->customer_name }} · {{ (int) $order->items_count }} {{ (int) $order->items_count === 1 ? 'item' : 'itens' }}
                                </span>
                            </a>
                            <button type="button" wire:click="accept({{ $order->id }})" wire:loading.attr="disabled" wire:target="accept({{ $order->id }})"
                                    class="btn-primary btn-sm shrink-0">
                                <x-icon name="check" class="size-4" stroke="2.4" /> Aceitar
                            </button>
                        </li>
                    @endforeach
                </ul>

                <footer class="flex items-center gap-3 border-t border-stone-100 bg-stone-50 px-4 py-2 text-xs text-stone-500">
                    <span class="flex-1">Aceitar inicia o preparo.
                        @if ($count > count($orders))
                            <a href="{{ route('orders.board') }}" wire:navigate class="font-semibold text-brand-700 hover:underline">Ver todos ({{ $count }})</a>
                        @endif
                    </span>
                    <button type="button" x-show="! silenced" @click="silence(); expanded = false" class="font-semibold text-stone-600 hover:text-stone-900">Silenciar</button>
                    <button type="button" x-show="silenced" x-cloak @click="expanded = false" class="font-semibold text-stone-600 hover:text-stone-900">Minimizar</button>
                </footer>
            </section>
        </div>
    @endif
</div>
