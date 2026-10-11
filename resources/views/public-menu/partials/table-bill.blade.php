{{-- Aba "Conta" do cardápio aberto pelo QR code da mesa: pedidos, total e chamar o garçom. --}}
@php
    $statusStyle = [
        'received' => ['Recebido', 'badge-blue'],
        'preparing' => ['Em preparo', 'badge-amber'],
        'ready' => ['Pronto', 'badge-green'],
        'completed' => ['Servido', 'badge-gray'],
    ];
    $called = session('table_call');
@endphp
<div>
    <h1 class="text-2xl font-extrabold tracking-tight">Mesa {{ $table->name }}</h1>
    <p class="mt-1 text-sm text-stone-500">Pedidos desta mesa. O pagamento é com o garçom ou no caixa.</p>
</div>

@if ($tableSent)
    {{-- Pedido enviado: limpa o carrinho da mesa. --}}
    <div class="alert-success" x-data x-init="$store.cart.clear(); history.replaceState(null, '', location.pathname + location.hash)">
        <x-icon name="check" class="mt-0.5 size-4" />
        <span>Pedido #{{ $tableSent }} enviado para a cozinha! Quando ficar pronto, o garçom leva até a mesa.</span>
    </div>
@endif

@if ($called)
    <div class="alert-success">
        <x-icon name="bell" class="mt-0.5 size-4" />
        <span>{{ $called === 'bill' ? 'Pronto! Avisamos que você pediu a conta.' : 'Pronto! O garçom já foi chamado.' }}</span>
    </div>
@endif

<div class="grid grid-cols-2 gap-2">
    <form method="POST" action="{{ $tableUrl }}/chamar">
        @csrf
        <input type="hidden" name="reason" value="waiter">
        <button type="submit" class="btn-secondary btn-lg w-full"><x-icon name="hand" class="size-5" /> Chamar garçom</button>
    </form>
    <form method="POST" action="{{ $tableUrl }}/chamar">
        @csrf
        <input type="hidden" name="reason" value="bill">
        <button type="submit" class="btn-secondary btn-lg w-full" @disabled(! $tableBill)><x-icon name="file" class="size-5" /> Pedir a conta</button>
    </form>
</div>

@if ($tableBill && $tableBill['orders']->isNotEmpty())
    <section class="card">
        <ul class="divide-y divide-stone-100">
            @foreach ($tableBill['orders'] as $order)
                @php([$label, $badge] = $statusStyle[$order->status->value] ?? [$order->status->label(), 'badge-gray'])
                <li class="px-4 py-3">
                    <p class="flex items-center gap-2 text-xs text-stone-500">
                        <span class="font-semibold text-stone-700">#{{ $order->number }}</span>
                        <span>{{ $order->created_at->timezone($timezone)->format('H:i') }}</span>
                        <span class="{{ $badge }} ml-auto">{{ $label }}</span>
                    </p>
                    <ul class="mt-1.5 space-y-1 text-sm">
                        @foreach ($order->items as $item)
                            <li class="flex justify-between gap-3">
                                <span class="min-w-0"><span class="font-semibold text-brand-600">{{ $item->quantity }}×</span> {{ $item->name }}
                                    @if ($item->addons->isNotEmpty())<span class="block text-xs text-stone-500">{{ $item->addons->pluck('name')->implode(', ') }}</span>@endif
                                </span>
                                <span class="whitespace-nowrap text-stone-700">{{ $item->total_cents->format() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
        <div class="space-y-1.5 border-t border-stone-100 px-4 py-4 text-sm">
            <div class="flex justify-between text-stone-600"><span>Consumo</span><span>{{ $tableBill['subtotal']->format() }}</span></div>
            @unless ($tableBill['service']->isZero())
                <div class="flex justify-between text-stone-600"><span>Serviço ({{ \App\Modules\Tables\Models\Command::serviceFeePercent() }}%)</span><span>{{ $tableBill['service']->format() }}</span></div>
            @endunless
            <div class="flex justify-between text-base font-bold"><span>Total</span><span>{{ $tableBill['total']->format() }}</span></div>
        </div>
    </section>
    <button type="button" x-data @click="location.reload()" class="btn-link mx-auto flex w-fit items-center gap-1"><x-icon name="refresh" class="size-4" /> Atualizar</button>
@else
    <div class="card">
        <x-ui.empty icon="utensils" title="Nenhum pedido nesta mesa ainda" :text="$ordering ? 'Escolha no cardápio e envie: o pedido vai direto para a cozinha.' : $tableState['reason']">
            <a href="#cardapio" class="btn-primary">Ver cardápio</a>
        </x-ui.empty>
    </div>
@endif
