@php use App\Support\Money; @endphp
<div class="space-y-6">
    <x-ui.page-header title="Cartão fidelidade" subtitle="A cada tantos pedidos, o cliente ganha um cupom só dele. Conta pedidos concluídos do cardápio online e do balcão, pelo telefone." />

    <div class="grid gap-4 sm:grid-cols-4">
        <x-ui.stat label="Clientes no programa" :value="$stats['members']" icon="users" tone="brand" hint="Com pelo menos um pedido concluído desde que foi ligado" />
        <x-ui.stat label="Prêmios ganhos" :value="$stats['rewards']" icon="gift" tone="violet" />
        <x-ui.stat label="Prêmios usados" :value="$stats['used']" icon="ticket" tone="green" />
        <x-ui.stat label="Vendas com prêmio" :value="Money::cents($stats['used_sales_cents'])->format()" icon="trending" tone="green" hint="Total dos pedidos em que o prêmio foi usado" />
    </div>

    <div class="grid gap-6 xl:grid-cols-[26rem_1fr]">
        <form wire:submit="save" class="card card-body space-y-5">
            <label class="flex items-start gap-3 rounded-xl border-2 p-3 {{ $enabled ? 'border-brand-600 bg-brand-50' : 'border-stone-200' }}">
                <input type="checkbox" wire:model.live="enabled" class="checkbox mt-0.5">
                <span><strong>Programa ligado</strong>
                    <span class="block text-xs text-stone-500">
                        @if ($startedAt)
                            Contando pedidos desde {{ \Carbon\CarbonImmutable::parse($startedAt)->timezone($timezone)->format('d/m/Y') }}.
                        @else
                            Conta os pedidos a partir de quando você ligar (os antigos não entram).
                        @endif
                    </span>
                </span>
            </label>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="goal" class="label">Pedidos para ganhar</label>
                    <input wire:model.live.debounce.400ms="goal" id="goal" type="number" min="2" max="50" class="input">
                    @error('goal') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="minOrder" class="label">Pedido mínimo (R$)</label>
                    <input wire:model.live.debounce.400ms="minOrder" id="minOrder" type="text" inputmode="decimal" class="input" placeholder="Qualquer valor">
                    @error('minOrder') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="hint -mt-3">O mínimo vale para os itens (sem a taxa de entrega). Pedidos menores não contam.</p>

            <div>
                <p class="label">Prêmio</p>
                <div class="grid grid-cols-3 gap-2">
                    @foreach (['fixed' => 'Valor fixo', 'percent' => 'Porcentagem', 'free_delivery' => 'Entrega grátis'] as $value => $label)
                        <label @class(['flex cursor-pointer items-center justify-center rounded-xl border-2 px-2 py-2.5 text-center text-sm font-semibold', 'border-brand-600 bg-brand-50 text-brand-800' => $rewardType === $value, 'border-stone-200' => $rewardType !== $value])>
                            <input type="radio" wire:model.live="rewardType" value="{{ $value }}" class="sr-only"> {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                @if ($rewardType === 'fixed')
                    <div>
                        <label for="rewardAmount" class="label">Desconto (R$)</label>
                        <input wire:model.live.debounce.400ms="rewardAmount" id="rewardAmount" type="text" inputmode="decimal" class="input">
                        @error('rewardAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @elseif ($rewardType === 'percent')
                    <div>
                        <label for="rewardPercent" class="label">Desconto (%)</label>
                        <input wire:model.live.debounce.400ms="rewardPercent" id="rewardPercent" type="number" min="1" max="100" class="input">
                        @error('rewardPercent') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div>
                    <label for="validDays" class="label">Prêmio vale por (dias)</label>
                    <input wire:model.live.debounce.400ms="validDays" id="validDays" type="number" min="1" max="365" class="input">
                    @error('validDays') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                @if ($saved)<span class="text-sm font-medium text-emerald-700"><x-icon name="check" class="inline size-4" /> Salvo</span>@endif
                <button type="submit" class="btn-primary">Salvar</button>
            </div>
        </form>

        <div class="space-y-6">
            {{-- Como o cliente vê (página do pedido e cardápio) --}}
            <section class="card card-body">
                <p class="stat-label">Como o cliente vê</p>
                <div class="mt-3 max-w-sm rounded-2xl border border-stone-200 p-4">
                    <p class="flex items-center gap-2 font-bold"><x-icon name="star" class="size-5 text-brand-600" /> Cartão fidelidade</p>
                    <p class="mt-1 text-sm text-stone-600">{{ $headline }}.</p>
                    <x-loyalty-stamps :stamps="min(3, $goalPreview - 1)" :goal="$goalPreview" class="mt-3" />
                    <p class="mt-2 text-sm font-semibold">{{ $goalPreview <= 4 ? 'Falta só 1 pedido para o seu prêmio!' : 'Faltam '.($goalPreview - 3).' pedidos para o seu prêmio.' }}</p>
                </div>
                <p class="hint mt-3">Aparece na página de acompanhamento do pedido e no topo do cardápio. Ao completar, o cliente recebe um cupom só dele (vale uma vez), mostrado na página do pedido, no aviso de entregue e no PDV quando o telefone é digitado.</p>
            </section>

            <section class="card overflow-hidden">
                <div class="card-header"><h2 class="font-semibold">Últimos prêmios</h2></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Cliente</th><th>Cupom</th><th>Ganhou em</th><th>Situação</th></tr></thead>
                        <tbody>
                            @forelse ($rewards as $reward)
                                @php
                                    $coupon = $reward->coupon;
                                    $usedBy = $coupon?->redemptions->first(fn ($r) => $r->order && $r->order->status !== $cancelled);
                                @endphp
                                <tr wire:key="reward-{{ $reward->id }}">
                                    <td>
                                        @if ($reward->customer && ! $reward->customer->trashed())
                                            <a href="{{ route('customers.show', $reward->customer) }}" class="font-medium hover:underline" wire:navigate>{{ $reward->customer->name }}</a>
                                        @else
                                            <span class="text-stone-500">{{ $reward->customer?->name ?? 'Cliente removido' }}</span>
                                        @endif
                                    </td>
                                    <td><span class="font-mono font-bold tracking-wide">{{ $coupon?->code ?? '—' }}</span> <span class="block text-xs text-stone-500">{{ $coupon?->benefit() }}</span></td>
                                    <td class="text-sm whitespace-nowrap text-stone-600">{{ $reward->created_at->timezone($timezone)->format('d/m/Y') }}</td>
                                    <td>
                                        @if ($usedBy)
                                            <a href="{{ route('orders.show', $usedBy->order) }}" class="badge-green" wire:navigate>Usado no #{{ $usedBy->order->number }}</a>
                                        @elseif ($coupon?->ends_at?->isPast())
                                            <span class="badge-gray">Venceu</span>
                                        @else
                                            <span class="badge-amber">Disponível até {{ $coupon?->ends_at?->timezone($timezone)->format('d/m') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><x-ui.empty icon="gift" title="Nenhum prêmio ainda" text="Quando um cliente completar o cartão, o prêmio aparece aqui." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
