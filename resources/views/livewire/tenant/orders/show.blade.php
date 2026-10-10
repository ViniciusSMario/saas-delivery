@php
    use App\Modules\Customers\Models\Customer;
    use App\Modules\Orders\Enums\Fulfillment;
    use App\Modules\Orders\Enums\OrderStatus;
    use App\Modules\Orders\Enums\PaymentStatus;
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <div>
            <a href="{{ route('orders.board') }}" class="inline-flex items-center gap-1 text-sm font-medium text-stone-500 hover:text-stone-800" wire:navigate><x-icon name="chevron-left" class="size-4" /> Painel</a>
            <h1 class="page-title mt-1">Pedido #{{ $order->number }} <span class="ml-2 text-base font-medium text-stone-500">{{ $order->status->label() }}</span></h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" onclick="window.print()" class="btn-secondary"><x-icon name="printer" class="size-4" /> Imprimir</button>
            <button type="button" wire:click="reprint" class="btn-secondary"><x-icon name="flame" class="size-4" /> Reimprimir nos setores</button>
            @can('orders.manage')
                @if ($order->payments->contains('status', PaymentStatus::Pending) && $order->status !== OrderStatus::Cancelled)
                    <button type="button" wire:click="markPaid" class="btn-secondary">Marcar como pago</button>
                @endif
                @if ($next)
                    <button type="button" wire:click="advance" class="btn-primary">{{ $next === OrderStatus::Completed && $order->fulfillment === Fulfillment::Delivery ? 'Entregue' : $next->label() }}</button>
                @endif
            @endcan
            @can('orders.cancel')
                @unless ($order->status->isFinal())
                    <button type="button" wire:click="$set('cancelling', true)" class="btn-secondary text-red-700">Cancelar pedido</button>
                @endunless
            @endcan
        </div>
    </div>

    @error('status') <div class="alert-error">{{ $message }}</div> @enderror

    @if ($cancelling)
        <form wire:submit="cancel" class="card card-body flex flex-wrap items-end gap-3 print:hidden">
            <div class="min-w-64 flex-1">
                <label for="reason" class="label">Motivo do cancelamento</label>
                <input wire:model="cancelReason" id="reason" type="text" class="input" maxlength="255">
                @error('cancelReason') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <button type="button" wire:click="$set('cancelling', false)" class="btn-secondary">Voltar</button>
            <button type="submit" class="btn-danger">Confirmar cancelamento</button>
        </form>
    @endif

    @if ($order->status === OrderStatus::Cancelled)
        <div class="alert-error">Cancelado em {{ $order->cancelled_at?->timezone($timezone)->format('d/m H:i') }}: {{ $order->cancel_reason }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card lg:col-span-2">
            <ul class="divide-y divide-stone-100">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-3 p-4 text-sm">
                        <div>
                            <p class="font-medium">{{ $item->quantity }}× {{ $item->name }} <span class="font-normal text-stone-500">({{ $item->unit_price_cents->format() }})</span></p>
                            @if ($item->description)<p class="text-xs text-stone-500">{{ $item->description }}</p>@endif
                            @foreach ($item->addons as $addon)
                                <p class="text-xs text-stone-500">+ {{ $addon->group_name }}: {{ $addon->name }} {{ $addon->unit_price_cents->isZero() ? '' : '('.$addon->unit_price_cents->format().')' }}</p>
                            @endforeach
                            @if ($item->notes)<p class="text-xs font-medium text-amber-700">Obs.: {{ $item->notes }}</p>@endif
                        </div>
                        <p class="whitespace-nowrap font-medium">{{ $item->total_cents->format() }}</p>
                    </li>
                @endforeach
            </ul>
            <div class="space-y-1 border-t border-stone-100 p-4 text-sm">
                <div class="flex justify-between"><span>Subtotal</span><span>{{ $order->subtotal_cents->format() }}</span></div>
                @unless ($order->discount_cents->isZero())
                    <div class="flex justify-between text-green-700"><span>Desconto</span><span>- {{ $order->discount_cents->format() }}</span></div>
                @endunless
                @if ($order->fulfillment === Fulfillment::Delivery)
                    <div class="flex justify-between"><span>Taxa de entrega</span><span>{{ $order->delivery_fee_cents->format() }}</span></div>
                @endif
                <div class="flex justify-between pt-1 text-base font-semibold"><span>Total</span><span>{{ $order->total_cents->format() }}</span></div>
            </div>
            @if ($order->notes)
                <p class="border-t border-stone-100 p-4 text-sm"><span class="font-medium">Observações:</span> {{ $order->notes }}</p>
            @endif
        </section>

        <div class="space-y-6">
            <section class="card card-body space-y-1 text-sm">
                <h2 class="section-title">{{ $order->fulfillment->label() }} · {{ $order->source->label() }}</h2>
                <p>{{ $order->customer_name ?? 'Sem identificação' }}</p>
                @if ($order->customer_phone)
                    <p><a href="https://wa.me/55{{ $order->customer_phone }}" target="_blank" class="btn-link">{{ Customer::formatPhone($order->customer_phone) }}</a></p>
                @endif
                @if ($order->fulfillment === Fulfillment::Delivery)
                    <p class="pt-1 text-stone-700">{{ $order->address() }}</p>
                    @if ($order->address_reference)<p class="text-xs text-stone-500">Ref.: {{ $order->address_reference }}</p>@endif
                @endif
                <p class="pt-1 text-xs text-stone-500">Feito em {{ $order->created_at->timezone($timezone)->format('d/m/Y H:i') }}{{ $order->createdBy ? ' por '.$order->createdBy->name : '' }}</p>
            </section>

            <section class="card card-body space-y-1 text-sm">
                <h2 class="section-title">Pagamento</h2>
                @foreach ($order->payments as $payment)
                    <p>{{ $payment->method->name }} · {{ $payment->amount_cents->format() }} · <span @class(['font-medium', 'text-green-700' => $payment->status === PaymentStatus::Paid, 'text-amber-700' => $payment->status === PaymentStatus::Pending])>{{ $payment->status->label() }}</span></p>
                    @if ($payment->change())
                        <p class="text-amber-800">Levar troco: {{ $payment->change()->format() }} (para {{ $payment->change_for_cents->format() }})</p>
                    @endif
                @endforeach
            </section>

            <section class="card card-body text-sm print:hidden">
                <h2 class="section-title">Histórico</h2>
                <ol class="mt-2 space-y-1">
                    @foreach ($order->history as $entry)
                        <li class="flex justify-between gap-2">
                            <span>{{ $entry->to_status->label() }}{{ $entry->user ? ' · '.$entry->user->name : '' }}{{ $entry->note ? ' · '.$entry->note : '' }}</span>
                            <span class="text-stone-500">{{ $entry->created_at->timezone($timezone)->format('H:i') }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>
    </div>
</div>
