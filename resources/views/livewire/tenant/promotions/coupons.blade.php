<div class="space-y-6">
    <x-ui.page-header title="Cupons de desconto" subtitle="Códigos que o cliente digita no fechamento do pedido pelo cardápio online.">
        <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Novo cupom</button>
    </x-ui.page-header>

    @php($totalUses = $stats->sum('uses'))
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Pedidos com cupom" :value="$totalUses" icon="ticket" tone="brand" />
        <x-ui.stat label="Desconto dado" :value="\App\Support\Money::cents((int) $stats->sum('discount'))->format()" icon="tag" tone="violet" />
        <x-ui.stat label="Vendas com cupom" :value="\App\Support\Money::cents((int) $stats->sum('sales'))->format()" icon="trending" tone="green" hint="Total dos pedidos, sem os cancelados" />
    </div>

    <div @class(['grid gap-6', 'xl:grid-cols-[1fr_26rem]' => $showForm])>
        <section class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Cupom</th><th>Desconto</th><th>Validade</th><th class="text-right">Usos</th><th class="text-right">Desconto dado</th><th class="text-right">Vendas</th><th class="text-right">Ações</th></tr></thead>
                    <tbody>
                        @forelse ($coupons as $coupon)
                            @php($row = $stats->get($coupon->id))
                            @php($expired = $coupon->ends_at?->isPast())
                            <tr wire:key="coupon-{{ $coupon->id }}" @class(['opacity-50' => ! $coupon->is_active || $expired])>
                                <td>
                                    <span class="font-mono font-bold tracking-wide">{{ $coupon->code }}</span>
                                    <span class="mt-0.5 flex flex-wrap gap-1">
                                        @unless ($coupon->is_active)<span class="badge-gray">pausado</span>@endunless
                                        @if ($expired)<span class="badge-gray">expirado</span>@endif
                                        @if ($coupon->is_public)<span class="badge-brand">no cardápio</span>@endif
                                        @if ($coupon->show_after_order)<span class="badge-violet">volte e ganhe</span>@endif
                                        @if ($coupon->first_order_only)<span class="badge-blue">1º pedido</span>@endif
                                    </span>
                                    @if ($coupon->description)<span class="block text-xs text-stone-500">{{ $coupon->description }}</span>@endif
                                </td>
                                <td class="text-sm">
                                    {{ $coupon->benefit() }}
                                    @if ($coupon->min_order_cents && ! $coupon->min_order_cents->isZero())<span class="block text-xs text-stone-500">mín. {{ $coupon->min_order_cents->format() }}</span>@endif
                                </td>
                                <td class="text-sm whitespace-nowrap text-stone-600">
                                    @if ($coupon->starts_at || $coupon->ends_at)
                                        {{ $coupon->starts_at?->timezone($timezone)->format('d/m/y') ?? 'hoje' }} – {{ $coupon->ends_at?->timezone($timezone)->format('d/m/y') ?? 'sem fim' }}
                                    @else
                                        Sem prazo
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">
                                    {{ (int) ($row->uses ?? 0) }}{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : '' }}
                                    @if ($row?->customers)<span class="block text-xs text-stone-500">{{ $row->customers }} {{ $row->customers == 1 ? 'cliente' : 'clientes' }}</span>@endif
                                </td>
                                <td class="text-right whitespace-nowrap tabular-nums">{{ \App\Support\Money::cents((int) ($row->discount ?? 0))->format() }}</td>
                                <td class="text-right whitespace-nowrap tabular-nums">{{ \App\Support\Money::cents((int) ($row->sales ?? 0))->format() }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" wire:click="toggle({{ $coupon->id }})" class="btn-ghost btn-sm">{{ $coupon->is_active ? 'Pausar' : 'Ativar' }}</button>
                                    <button type="button" wire:click="edit({{ $coupon->id }})" class="btn-icon btn-ghost size-8" title="Editar"><x-icon name="edit" class="size-4" /></button>
                                    <button type="button" wire:click="delete({{ $coupon->id }})" wire:confirm="Excluir o cupom {{ $coupon->code }}? Os pedidos que já usaram continuam mostrando o código." class="btn-icon btn-ghost size-8 text-red-600 hover:bg-red-50" title="Excluir"><x-icon name="trash" class="size-4" /></button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7">
                                <x-ui.empty icon="ticket" title="Nenhum cupom ainda" text="Crie um cupom de primeiro pedido para atrair clientes novos, ou um 'volte e ganhe' para quem já comprou.">
                                    <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Criar cupom</button>
                                </x-ui.empty>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($showForm)
            <form wire:submit="save" class="card card-body h-fit space-y-4 xl:sticky xl:top-6">
                <div class="flex items-center justify-between">
                    <h2 class="section-title">{{ $editingId ? 'Editar cupom' : 'Novo cupom' }}</h2>
                    <button type="button" wire:click="cancel" class="btn-icon btn-ghost size-8" aria-label="Fechar"><x-icon name="x" class="size-5" /></button>
                </div>

                <div>
                    <label for="code" class="label">Código</label>
                    <div class="flex gap-2">
                        <input wire:model="code" id="code" type="text" class="input min-w-0 flex-1 font-mono uppercase" maxlength="30" placeholder="PRIMEIRA10">
                        <button type="button" wire:click="generateCode" class="btn-secondary shrink-0" title="Gerar um código"><x-icon name="refresh" class="size-4" /></button>
                    </div>
                    @error('code') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="description" class="label">Descrição interna (opcional)</label>
                    <input wire:model="description" id="description" type="text" class="input" maxlength="160" placeholder="Ex.: Campanha do Instagram">
                </div>

                <div>
                    <p class="label">Tipo de desconto</p>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach (\App\Modules\Promotions\CouponType::cases() as $option)
                            <label @class(['flex cursor-pointer items-center justify-center rounded-xl border-2 px-2 py-2.5 text-center text-sm font-semibold', 'border-brand-600 bg-brand-50 text-brand-800' => $type === $option->value, 'border-stone-200' => $type !== $option->value])>
                                <input type="radio" wire:model.live="type" value="{{ $option->value }}" class="sr-only"> {{ $option->label() }}
                            </label>
                        @endforeach
                    </div>
                </div>

                @if ($type === 'percent')
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="percent" class="label">Desconto (%)</label>
                            <input wire:model="percent" id="percent" type="number" min="1" max="100" class="input">
                            @error('percent') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="maxDiscount" class="label">Até (R$, opcional)</label>
                            <input wire:model="maxDiscount" id="maxDiscount" type="text" inputmode="decimal" class="input" placeholder="Sem teto">
                            @error('maxDiscount') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @elseif ($type === 'fixed')
                    <div>
                        <label for="amount" class="label">Valor do desconto (R$)</label>
                        <input wire:model="amount" id="amount" type="text" inputmode="decimal" class="input max-w-40" placeholder="10,00">
                        @error('amount') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                @else
                    <p class="rounded-xl bg-stone-50 p-3 text-sm text-stone-600">O cliente não paga a taxa de entrega. Só vale para pedidos com entrega. O entregador continua recebendo a taxa nos relatórios.</p>
                @endif

                <div>
                    <label for="minOrder" class="label">Pedido mínimo (R$, opcional)</label>
                    <input wire:model="minOrder" id="minOrder" type="text" inputmode="decimal" class="input max-w-40" placeholder="Sem mínimo">
                    @error('minOrder') <p class="field-error">{{ $message }}</p> @enderror
                    <p class="hint">Sobre o valor dos itens, sem a taxa de entrega.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="startsAt" class="label">Começa em</label>
                        <input wire:model="startsAt" id="startsAt" type="date" class="input">
                        @error('startsAt') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="endsAt" class="label">Termina em</label>
                        <input wire:model="endsAt" id="endsAt" type="date" class="input">
                        @error('endsAt') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="usageLimit" class="label">Usos no total</label>
                        <input wire:model="usageLimit" id="usageLimit" type="number" min="1" class="input" placeholder="Sem limite">
                        @error('usageLimit') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="perCustomerLimit" class="label">Usos por cliente</label>
                        <input wire:model="perCustomerLimit" id="perCustomerLimit" type="number" min="1" class="input" placeholder="Sem limite">
                        @error('perCustomerLimit') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="hint -mt-2">O cliente é reconhecido pelo telefone. Pedido cancelado devolve o uso.</p>

                <div class="space-y-2.5 rounded-xl border border-stone-200 p-3">
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="firstOrderOnly" class="checkbox mt-0.5"> <span><strong>Só no primeiro pedido</strong><span class="block text-xs text-stone-500">Para atrair clientes novos.</span></span></label>
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="isPublic" class="checkbox mt-0.5"> <span><strong>Divulgar no cardápio</strong><span class="block text-xs text-stone-500">Aparece no topo do cardápio com o botão "Usar".</span></span></label>
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="showAfterOrder" class="checkbox mt-0.5"> <span><strong>Volte e ganhe</strong><span class="block text-xs text-stone-500">Oferecido na página do pedido assim que ele é entregue, para o próximo pedido.</span></span></label>
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="isActive" class="checkbox mt-0.5"> <span><strong>Ativo</strong></span></label>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancel" class="btn-secondary">Cancelar</button>
                    <button type="submit" class="btn-primary"><x-icon name="check" class="size-4" /> Salvar</button>
                </div>
            </form>
        @endif
    </div>
</div>
