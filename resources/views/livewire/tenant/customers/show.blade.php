@php $tz = \App\Modules\Establishment\Models\Setting::read('timezone', 'America/Sao_Paulo'); @endphp
<div class="space-y-6">
    <div>
        <a href="{{ route('customers.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-stone-500 hover:text-stone-800" wire:navigate><x-icon name="chevron-left" class="size-4" /> Clientes</a>
        <h1 class="page-title mt-1">{{ $customer->name }}</h1>
        <p class="text-sm text-stone-500">{{ $count }} pedido(s) · {{ $spent->format() }} no total · ticket médio {{ $average->format() }}
            @if ($favorite->isNotEmpty()) · mais pede: {{ $favorite->pluck('name')->implode(', ') }} @endif
        </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="save" class="card card-body space-y-3">
            <h2 class="section-title">Dados</h2>
            <div>
                <label for="name" class="label">Nome</label>
                <input wire:model="name" id="name" type="text" class="input">
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="phone" class="label">Telefone</label>
                <input wire:model="phone" id="phone" type="tel" data-mask="phone" class="input">
                @error('phone') <p class="field-error">{{ $message }}</p> @enderror
                <p class="hint"><a href="https://wa.me/55{{ $customer->phone }}" target="_blank" class="btn-link text-xs">Abrir no WhatsApp</a></p>
            </div>
            <div>
                <label for="email" class="label">E-mail</label>
                <input wire:model="email" id="email" type="email" class="input">
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="notes" class="label">Observações</label>
                <textarea wire:model="notes" id="notes" rows="3" class="input" placeholder="Ex.: alérgico a camarão, prefere entrega no portão"></textarea>
            </div>
            <button type="submit" class="btn-primary"><x-icon name="check" class="size-4" /> Salvar</button>
        </form>

        <div class="space-y-6 lg:col-span-2">
            @if ($loyalty)
                <section class="card card-body">
                    <h2 class="section-title flex items-center gap-2"><x-icon name="star" class="size-5 text-brand-600" /> Cartão fidelidade</h2>
                    <x-loyalty-stamps :stamps="$loyalty['stamps']" :goal="$loyalty['goal']" class="mt-3" />
                    <p class="mt-2 text-sm text-stone-600">{{ $loyalty['stamps'] }} de {{ $loyalty['goal'] }} pedidos · {{ $loyalty['missing'] === 1 ? 'falta 1' : 'faltam '.$loyalty['missing'] }} para o próximo prêmio.</p>
                    @foreach ($loyalty['rewards'] as $reward)
                        <p class="mt-2 text-sm"><span class="badge-amber">Prêmio disponível</span> <strong class="font-mono">{{ $reward->code }}</strong> · {{ $reward->benefit() }}{{ $reward->ends_at ? ' · até '.$reward->ends_at->timezone($tz)->format('d/m') : '' }}</p>
                    @endforeach
                </section>
            @endif
            <section class="card card-body">
                <h2 class="section-title">Endereços</h2>
                <ul class="mt-2 divide-y divide-stone-100 text-sm">
                    @forelse ($customer->addresses as $address)
                        <li class="flex justify-between gap-3 py-2" wire:key="ad-{{ $address->id }}">
                            <span>
                                {{ $address->street }}, {{ $address->number }}{{ $address->complement ? ' · '.$address->complement : '' }} · {{ $address->district }}
                                @if ($address->reference)<span class="block text-xs text-stone-500">Ref.: {{ $address->reference }}</span>@endif
                            </span>
                            <button type="button" wire:click="removeAddress({{ $address->id }})" class="text-xs text-red-700 hover:underline">Remover</button>
                        </li>
                    @empty
                        <li class="py-2 text-stone-500">Nenhum endereço salvo.</li>
                    @endforelse
                </ul>
            </section>

            <section class="card overflow-x-auto">
                <div class="card-header"><h2 class="section-title">Pedidos</h2></div>
                <table class="table table-cards">
                    <thead><tr><th>Pedido</th><th>Quando</th><th>Status</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr wire:key="o-{{ $order->id }}">
                                <td>@can('orders.view')<a href="{{ route('orders.show', $order) }}" class="font-medium hover:underline" wire:navigate>#{{ $order->number }}</a>@else #{{ $order->number }} @endcan</td>
                                <td class="text-stone-500">{{ $order->created_at->timezone($tz)->format('d/m/Y H:i') }} · {{ $order->fulfillment->label() }}</td>
                                <td>{{ $order->status->label() }}</td>
                                <td class="text-right whitespace-nowrap">{{ $order->total_cents->format() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-ui.empty icon="orders" title="Nenhum pedido" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
            {{ $orders->links() }}

            <section class="card card-body">
                <h2 class="section-title">Exclusão de dados (LGPD)</h2>
                <p class="mt-1 text-sm text-stone-600">Apaga nome, telefone, e-mail, endereços e observações do cliente e dos pedidos dele. Os pedidos continuam no histórico financeiro, sem identificação. Não dá para desfazer.</p>
                <button type="button" wire:click="anonymize" wire:confirm="Apagar os dados pessoais de {{ $customer->name }}? Não dá para desfazer." class="btn-danger mt-3">Apagar dados pessoais</button>
            </section>
        </div>
    </div>
</div>
