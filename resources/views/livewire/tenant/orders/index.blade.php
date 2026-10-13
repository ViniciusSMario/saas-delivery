<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="page-title">Pedidos</h1>
            <p class="mt-1 text-sm text-stone-500">{{ $summaryCount }} pedido(s) · {{ $summaryTotal->format() }} (sem cancelados)</p>
        </div>
        <a href="{{ route('orders.board') }}" class="btn-secondary" wire:navigate>Painel</a>
    </div>

    <div class="flex flex-wrap gap-3">
        <input wire:model.live="date" type="date" class="input w-auto" aria-label="Dia">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Número, nome ou telefone" class="input max-w-xs" aria-label="Buscar">
        <select wire:model.live="status" class="input w-auto" aria-label="Status">
            <option value="">Todos os status</option>
            @foreach ($statuses as $s)
                <option value="{{ $s->value }}">{{ $s->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="card overflow-x-auto">
        <table class="table table-cards">
            <thead>
                <tr><th>Pedido</th><th>Cliente</th><th>Status</th><th>Pagamento</th><th class="text-right">Total</th></tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    @php($payment = $order->payments->first())
                    <tr wire:key="o-{{ $order->id }}">
                        <td>
                            <a href="{{ route('orders.show', $order) }}" class="font-semibold hover:underline" wire:navigate>#{{ $order->number }}</a>
                            <p class="text-xs text-stone-500">{{ $order->created_at->timezone($timezone)->format('H:i') }} · {{ $order->fulfillment->label() }} · {{ $order->source->label() }}</p>
                        </td>
                        <td>
                            {{ $order->customer_name ?? '—' }}
                            @if ($order->customer_phone)<p class="text-xs text-stone-500">{{ \App\Modules\Customers\Models\Customer::formatPhone($order->customer_phone) }}</p>@endif
                        </td>
                        <td>{{ $order->status->label() }}</td>
                        <td class="text-stone-600">{{ $payment?->method->name }} · {{ $payment?->status->label() }}</td>
                        <td class="text-right font-medium whitespace-nowrap">{{ $order->total_cents->format() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">
                        @if (trim($search) !== '' || $status !== '')
                            <x-ui.empty icon="search" title="Nenhum pedido encontrado" text="Nada com essa busca ou situação neste dia.">
                                <button type="button" wire:click="clearFilters" class="btn-secondary">Limpar filtros</button>
                            </x-ui.empty>
                        @else
                            <x-ui.empty icon="orders" title="Nenhum pedido neste dia" text="Os pedidos do cardápio online, do balcão e das mesas aparecem aqui.">
                                @if ($date !== $today)
                                    <button type="button" wire:click="$set('date', @js($today))" class="btn-secondary">Ver hoje</button>
                                @endif
                                @can('pos.use')
                                    <a href="{{ route('orders.counter') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> Venda no balcão</a>
                                @endcan
                            </x-ui.empty>
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $orders->links() }}
</div>
