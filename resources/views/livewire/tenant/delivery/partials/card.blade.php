{{-- Cartão de entrega (botões grandes, para usar com uma mão). Espera: $order, $mode (ready | mine | other), $city, $manager, $couriers. --}}
@php
    $payment = $order->payments->first();
    $toCollect = $payment && $payment->status === \App\Modules\Orders\Enums\PaymentStatus::Pending;
    $mapUrl = 'https://www.google.com/maps/search/?api=1&query='.urlencode(\App\Livewire\Tenant\Delivery\Board::mapAddress($order, $city));
    $digits = preg_replace('/\D/', '', (string) $order->customer_phone);
    $since = $mode === 'ready' ? $order->updated_at : $order->dispatched_at;
    $change = $toCollect ? $payment->change() : null;
@endphp
<article wire:key="dl-{{ $mode }}-{{ $order->id }}" class="card overflow-hidden">
    <div class="space-y-3 p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-2xl leading-none font-extrabold tracking-tight">#{{ $order->number }}</p>
                <p class="mt-1 truncate font-medium text-stone-700">{{ $order->customer_name }}</p>
            </div>
            <div class="shrink-0 text-right">
                @if ($order->address_district)<span class="badge-blue text-sm">{{ $order->address_district }}</span>@endif
                @if ($since)<p class="mt-1 text-xs text-stone-500">{{ $mode === 'ready' ? 'pronto' : 'saiu' }} {{ $since->locale('pt_BR')->diffForHumans(short: true) }}</p>@endif
            </div>
        </div>

        <a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="flex items-start gap-3 rounded-xl bg-stone-50 p-3 active:bg-stone-100">
            <x-icon name="pin" class="mt-0.5 size-5 shrink-0 text-brand-600" />
            <span class="min-w-0 flex-1">
                <span class="block font-semibold">{{ $order->address() }}</span>
                @if ($order->address_reference)<span class="block text-sm text-stone-500">Ref.: {{ $order->address_reference }}</span>@endif
            </span>
            <x-icon name="external" class="mt-0.5 size-4 shrink-0 text-stone-400" />
        </a>

        @if ($toCollect)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5">
                <p class="flex items-baseline justify-between gap-2">
                    <span class="text-sm font-semibold text-amber-900">Cobrar · {{ $payment->method?->name }}</span>
                    <span class="text-xl font-extrabold text-amber-900 tabular-nums">{{ $order->total_cents->format() }}</span>
                </p>
                @if ($change)
                    <p class="mt-0.5 text-sm text-amber-900">Cliente paga com {{ $payment->change_for_cents->format() }}: <strong>levar {{ $change->format() }} de troco</strong></p>
                @endif
            </div>
        @else
            <p class="flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2.5 text-sm font-semibold text-emerald-800"><x-icon name="check" class="size-4" stroke="2.6" /> Pago. Não precisa cobrar.</p>
        @endif

        <div class="flex flex-wrap items-center gap-2 text-sm text-stone-600">
            <span>{{ $order->items->sum('quantity') }} item(ns)</span>
            @if ($mode === 'other' && $order->courier)<span class="badge-violet">{{ $order->courier->name }}</span>@endif
        </div>
        @if ($order->notes)
            <p class="rounded-lg bg-amber-50 px-2.5 py-1.5 text-sm font-medium text-amber-900">Obs.: {{ $order->notes }}</p>
        @endif
    </div>

    <div class="space-y-2 border-t border-stone-100 bg-stone-50/70 p-3">
        @if ($mode !== 'ready')
            <div class="grid grid-cols-3 gap-2">
                <a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="btn-secondary h-12 flex-col gap-0.5 rounded-xl text-xs"><x-icon name="pin" class="size-5" /> Mapa</a>
                @if ($digits)
                    <a href="tel:{{ $digits }}" class="btn-secondary h-12 flex-col gap-0.5 rounded-xl text-xs"><x-icon name="phone" class="size-5" /> Ligar</a>
                    <a href="{{ \App\Modules\Messaging\OrderMessages::link($order, 'dispatched') }}" target="_blank" rel="noopener" class="btn-secondary h-12 flex-col gap-0.5 rounded-xl text-xs text-emerald-700"><x-icon name="share" class="size-5" /> WhatsApp</a>
                @else
                    <span class="col-span-2 flex items-center justify-center rounded-xl border border-dashed border-stone-200 text-xs text-stone-400">Sem telefone</span>
                @endif
            </div>
        @endif

        @if ($mode === 'ready')
            @if ($manager && $couriers->isNotEmpty())
                <select class="input h-12" aria-label="Escolher entregador"
                        x-data @change="if ($event.target.value) $wire.assign({{ $order->id }}, parseInt($event.target.value)); $event.target.value = ''">
                    <option value="">Mandar com outro entregador…</option>
                    @foreach ($couriers as $courier)
                        <option value="{{ $courier->id }}">{{ $courier->name }}</option>
                    @endforeach
                </select>
            @endif
            @can('deliveries.operate')
                <button type="button" wire:click="take({{ $order->id }})" wire:loading.attr="disabled" class="btn-primary btn-xl"><x-icon name="truck" class="size-6" /> Pegar entrega</button>
            @endcan
        @elseif ($mode === 'mine')
            <button type="button" wire:click="complete({{ $order->id }})" wire:loading.attr="disabled"
                    wire:confirm="Confirmar a entrega do pedido #{{ $order->number }}{{ $toCollect ? ' e que você recebeu '.$order->total_cents->format() : '' }}?"
                    class="btn-success btn-xl">
                <x-icon name="check" class="size-6" stroke="2.4" /> {{ $toCollect ? 'Entregue e recebi' : 'Entregue' }}
            </button>
        @else
            <div class="grid grid-cols-[1fr_auto] gap-2">
                @if ($couriers->isNotEmpty())
                    <select class="input h-12" aria-label="Trocar entregador"
                            x-data @change="if ($event.target.value) $wire.assign({{ $order->id }}, parseInt($event.target.value)); $event.target.value = ''">
                        <option value="">Trocar entregador…</option>
                        @foreach ($couriers as $courier)
                            <option value="{{ $courier->id }}">{{ $courier->name }}</option>
                        @endforeach
                    </select>
                @endif
                <button type="button" wire:click="complete({{ $order->id }})" wire:confirm="Marcar o pedido #{{ $order->number }} como entregue?" class="btn-secondary h-12 rounded-xl">Entregue</button>
            </div>
        @endif
    </div>
</article>
