@php
    use App\Modules\Customers\Models\Customer;
    $tz = \App\Modules\Establishment\Models\Setting::read('timezone', 'America/Sao_Paulo');
@endphp
<div class="space-y-6">
    <div>
        <h1 class="page-title">Clientes</h1>
        <p class="mt-1 text-sm text-stone-500">Cadastrados automaticamente pelo telefone nos pedidos online e de balcão.</p>
    </div>

    <div class="flex flex-wrap gap-3">
        <input wire:model.live.debounce.300ms="search" type="search" class="input max-w-xs" placeholder="Nome ou telefone" aria-label="Buscar cliente">
        <select wire:model.live="sort" class="input w-auto" aria-label="Ordenar">
            <option value="recent">Último pedido</option>
            <option value="spent">Mais gastou</option>
            <option value="orders">Mais pedidos</option>
            <option value="name">Nome</option>
        </select>
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Cliente</th><th class="text-right">Pedidos</th><th class="text-right">Total gasto</th><th class="text-right">Ticket médio</th><th>Último pedido</th></tr></thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr wire:key="c-{{ $customer->id }}">
                        <td>
                            <a href="{{ route('customers.show', $customer) }}" class="font-medium text-stone-900 hover:underline" wire:navigate>{{ $customer->name }}</a>
                            <p class="text-xs text-stone-500">{{ Customer::formatPhone($customer->phone) }}</p>
                        </td>
                        <td class="text-right">{{ $customer->orders_count }}</td>
                        <td class="text-right whitespace-nowrap">{{ \App\Support\Money::cents((int) $customer->spent_cents)->format() }}</td>
                        <td class="text-right whitespace-nowrap">{{ \App\Support\Money::cents($customer->orders_count ? intdiv((int) $customer->spent_cents, $customer->orders_count) : 0)->format() }}</td>
                        <td class="text-stone-500">{{ $customer->last_order_at?->timezone($tz)->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-ui.empty icon="users" title="Nenhum cliente encontrado" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $customers->links() }}
</div>
