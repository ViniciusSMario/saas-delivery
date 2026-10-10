{{-- Cartão de entrega. Espera: $order, $mode (ready | mine | other), $city, $manager, $couriers. --}}
@php
    $payment = $order->payments->first();
    $toCollect = $payment && $payment->status === \App\Modules\Orders\Enums\PaymentStatus::Pending;
    $mapUrl = 'https://www.google.com/maps/search/?api=1&query='.urlencode(\App\Livewire\Tenant\Delivery\Board::mapAddress($order, $city));
    $digits = preg_replace('/\D/', '', (string) $order->customer_phone);
    $since = $mode === 'ready' ? $order->updated_at : $order->dispatched_at;
@endphp
<article wire:key="dl-{{ $mode }}-{{ $order->id }}" class="card overflow-hidden">
    <div class="space-y-3 p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xl font-extrabold tracking-tight">#{{ $order->number }}</p>
                <p class="text-sm font-medium text-stone-700">{{ $order->customer_name }}</p>
            </div>
            <div class="text-right">
                @if ($order->address_district)<span class="badge-blue">{{ $order->address_district }}</span>@endif
                @if ($since)<p class="mt-1 text-xs text-stone-500">{{ $mode === 'ready' ? 'pronto' : 'saiu' }} {{ $since->locale('pt_BR')->diffForHumans(short: true) }}</p>@endif
            </div>
        </div>

        <div class="flex items-start gap-2 rounded-xl bg-stone-50 p-3 text-sm">
            <x-icon name="pin" class="mt-0.5 size-4 shrink-0 text-brand-600" />
            <div class="min-w-0">
                <p class="font-medium">{{ $order->address() }}</p>
                @if ($order->address_reference)<p class="text-xs text-stone-500">Ref.: {{ $order->address_reference }}</p>@endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
            <span class="text-stone-600">{{ $order->items->sum('quantity') }} item(ns)</span>
            @if ($toCollect)
                <span class="font-bold text-amber-700">Cobrar {{ $order->total_cents->format() }} · {{ $payment->method?->name }}</span>
                @if ($payment->change())<span class="badge-amber">Troco: levar {{ $payment->change()->format() }}</span>@endif
            @else
                <span class="font-semibold text-emerald-700">Pago</span>
            @endif
            @if ($mode === 'other' && $order->courier)<span class="badge-violet">{{ $order->courier->name }}</span>@endif
        </div>
        @if ($order->notes)
            <p class="rounded-lg bg-amber-50 px-2.5 py-1.5 text-xs font-medium text-amber-900">Obs.: {{ $order->notes }}</p>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-2 border-t border-stone-100 bg-stone-50/70 px-4 py-3">
        <a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="btn-secondary btn-sm"><x-icon name="pin" class="size-4" /> Mapa</a>
        @if ($digits)
            <a href="tel:{{ $digits }}" class="btn-ghost btn-sm" title="Ligar"><x-icon name="phone" class="size-4" /></a>
            <a href="{{ \App\Modules\Messaging\OrderMessages::link($order, $mode === 'ready' ? null : 'dispatched') }}" target="_blank" rel="noopener" class="btn-ghost btn-sm text-emerald-700" title="Avisar o cliente no WhatsApp">WhatsApp</a>
        @endif
        <div class="ml-auto flex flex-wrap gap-2">
            @if ($mode === 'ready')
                @if ($manager && $couriers->isNotEmpty())
                    <select class="input h-8 w-auto py-0 text-xs" aria-label="Escolher entregador"
                            x-data @change="if ($event.target.value) $wire.assign({{ $order->id }}, parseInt($event.target.value)); $event.target.value = ''">
                        <option value="">Entregar com…</option>
                        @foreach ($couriers as $courier)
                            <option value="{{ $courier->id }}">{{ $courier->name }}</option>
                        @endforeach
                    </select>
                @endif
                @can('deliveries.operate')
                    <button type="button" wire:click="take({{ $order->id }})" wire:loading.attr="disabled" class="btn-primary btn-sm"><x-icon name="truck" class="size-4" /> Pegar entrega</button>
                @endcan
            @elseif ($mode === 'mine')
                <button type="button" wire:click="complete({{ $order->id }})" wire:confirm="Confirmar a entrega do pedido #{{ $order->number }}{{ $toCollect ? ' e o recebimento de '.$order->total_cents->format() : '' }}?" class="btn-success btn-sm">
                    <x-icon name="check" class="size-4" /> Entregue
                </button>
            @else
                @if ($couriers->isNotEmpty())
                    <select class="input h-8 w-auto py-0 text-xs" aria-label="Trocar entregador"
                            x-data @change="if ($event.target.value) $wire.assign({{ $order->id }}, parseInt($event.target.value)); $event.target.value = ''">
                        <option value="">Trocar entregador…</option>
                        @foreach ($couriers as $courier)
                            <option value="{{ $courier->id }}">{{ $courier->name }}</option>
                        @endforeach
                    </select>
                @endif
                <button type="button" wire:click="complete({{ $order->id }})" wire:confirm="Marcar o pedido #{{ $order->number }} como entregue?" class="btn-secondary btn-sm">Entregue</button>
            @endif
        </div>
    </div>
</article>
