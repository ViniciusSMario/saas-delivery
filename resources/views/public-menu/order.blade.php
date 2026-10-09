@php
    use App\Modules\Orders\Enums\Fulfillment;
    use App\Modules\Orders\Enums\OrderStatus;

    $steps = $order->fulfillment === Fulfillment::Delivery
        ? [OrderStatus::Received, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Dispatched, OrderStatus::Completed]
        : [OrderStatus::Received, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Completed];
    $reached = $order->history->pluck('to_status')->map->value->all();
    $payment = $order->payments->first();
@endphp
<x-layouts.public :title="'Pedido #'.$order->number.' · '.$establishment->trade_name">
    @unless ($order->status->isFinal())
        {{-- Atualiza sozinho enquanto o pedido está em andamento. --}}
        <meta http-equiv="refresh" content="20">
    @endunless

    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-2xl items-center justify-between gap-3 px-4 py-4">
            <a href="{{ $menuUrl }}" class="btn-link">← Cardápio</a>
            <p class="truncate font-semibold">{{ $establishment->trade_name }}</p>
        </div>
    </header>

    <main class="mx-auto max-w-2xl space-y-6 px-4 py-6">
        @if (request()->boolean('novo'))
            <div class="alert-success">Pedido enviado! Guarde este link para acompanhar.</div>
        @endif

        <section class="card card-body text-center">
            <p class="text-sm text-stone-500">Pedido</p>
            <p class="text-3xl font-bold">#{{ $order->number }}</p>
            <p @class(['mt-2 text-lg font-semibold', 'text-red-700' => $order->status === OrderStatus::Cancelled, 'text-green-700' => $order->status === OrderStatus::Completed])>
                {{ $order->status->customerLabel($order->fulfillment) }}
            </p>
            @if ($order->status === OrderStatus::Cancelled && $order->cancel_reason)
                <p class="mt-1 text-sm text-stone-600">Motivo: {{ $order->cancel_reason }}</p>
            @endif

            @if ($order->status !== OrderStatus::Cancelled)
                <ol class="mt-6 flex items-start justify-between gap-1 text-xs">
                    @foreach ($steps as $step)
                        @php($done = in_array($step->value, $reached, true))
                        <li class="flex flex-1 flex-col items-center gap-1">
                            <span @class(['size-3 rounded-full', 'bg-brand-600' => $done, 'bg-stone-300' => ! $done])></span>
                            <span @class(['text-stone-900' => $done, 'text-stone-400' => ! $done])>{{ $step->label() }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section class="card">
            <ul class="divide-y divide-stone-100">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-3 p-4 text-sm">
                        <div>
                            <p class="font-medium">{{ $item->quantity }}× {{ $item->name }}</p>
                            @if ($item->description)<p class="text-xs text-stone-500">{{ $item->description }}</p>@endif
                            @if ($item->addons->isNotEmpty())<p class="text-xs text-stone-500">{{ $item->addons->pluck('name')->implode(', ') }}</p>@endif
                            @if ($item->notes)<p class="text-xs text-stone-500">Obs.: {{ $item->notes }}</p>@endif
                        </div>
                        <p class="whitespace-nowrap">{{ $item->total_cents->format() }}</p>
                    </li>
                @endforeach
            </ul>
            <div class="space-y-1 border-t border-stone-100 p-4 text-sm">
                <div class="flex justify-between"><span>Subtotal</span><span>{{ $order->subtotal_cents->format() }}</span></div>
                @unless ($order->discount_cents->isZero())
                    <div class="flex justify-between"><span>Desconto</span><span>- {{ $order->discount_cents->format() }}</span></div>
                @endunless
                @if ($order->fulfillment === Fulfillment::Delivery)
                    <div class="flex justify-between"><span>Entrega</span><span>{{ $order->delivery_fee_cents->isZero() ? 'Grátis' : $order->delivery_fee_cents->format() }}</span></div>
                @endif
                <div class="flex justify-between pt-1 text-base font-semibold"><span>Total</span><span>{{ $order->total_cents->format() }}</span></div>
            </div>
        </section>

        <section class="card card-body grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <p class="font-semibold">{{ $order->fulfillment->label() }}</p>
                @if ($order->fulfillment === Fulfillment::Delivery)
                    <p class="text-stone-600">{{ $order->address() }}</p>
                    @if ($order->address_reference)<p class="text-xs text-stone-500">Ref.: {{ $order->address_reference }}</p>@endif
                @else
                    <p class="text-stone-600">{{ $establishment->address() ?: 'No estabelecimento' }}</p>
                @endif
            </div>
            <div>
                <p class="font-semibold">Pagamento</p>
                <p class="text-stone-600">{{ $payment?->method->name }} · {{ $payment?->status->label() }}</p>
                @if ($payment?->change())
                    <p class="text-xs text-stone-500">Troco para {{ $payment->change_for_cents->format() }} ({{ $payment->change()->format() }} de troco)</p>
                @endif
            </div>
            <p class="text-xs text-stone-500 sm:col-span-2">Feito em {{ $order->created_at->timezone($timezone)->format('d/m/Y \à\s H:i') }}{{ $establishment->phone ? ' · Dúvidas: '.$establishment->phone : '' }}</p>
        </section>
    </main>

    @if (request()->boolean('novo'))
        <script>
            try { localStorage.removeItem('cart:{{ tenant()->getKey() }}'); } catch (e) {}
        </script>
    @endif
</x-layouts.public>
