@php
    $fulfillments = ['counter' => ['Balcão', 'pos'], 'pickup' => ['Retirada', 'bag'], 'delivery' => ['Entrega', 'truck']];
@endphp
<div class="grid gap-6 xl:grid-cols-[1fr_25rem]">
    {{-- Produtos --}}
    <div class="min-w-0 space-y-4">
        <x-ui.page-header title="PDV" subtitle="Venda no balcão, retirada ou entrega.">
            <a href="{{ route('cash.current') }}" class="btn-secondary" wire:navigate><x-icon name="wallet" class="size-4" /> Caixa</a>
            <a href="{{ route('orders.board') }}" class="btn-secondary" wire:navigate><x-icon name="flame" class="size-4" /> Painel</a>
        </x-ui.page-header>

        @if (! $session)
            <div class="alert-warning">
                <x-icon name="alert" class="mt-0.5 size-4" />
                <span>
                    Seu caixa está fechado: vendas no balcão (pagas na hora) precisam de caixa aberto.
                    <a href="{{ route('cash.current') }}" class="font-semibold underline" wire:navigate>Abrir caixa</a>
                </span>
            </div>
        @endif

        @if ($awaiting->isNotEmpty())
            <div class="card flex flex-wrap items-center gap-2 border-amber-200 bg-amber-50/60 p-4">
                <p class="mr-1 flex items-center gap-2 text-sm font-semibold text-amber-900"><x-icon name="bell" class="size-4" /> Contas pedidas:</p>
                @foreach ($awaiting as $command)
                    <a href="{{ route('tables.command', $command) }}" class="btn-secondary btn-sm" wire:navigate wire:key="aw-{{ $command->id }}">
                        {{ $command->displayName() }} · #{{ $command->number }}
                    </a>
                @endforeach
            </div>
        @endif

        @include('livewire.tenant.partials.product-picker')
    </div>

    {{-- Carrinho e fechamento --}}
    <form wire:submit="checkout" class="card flex h-fit flex-col overflow-hidden xl:sticky xl:top-6">
        <div class="card-header">
            <h2 class="section-title flex items-center gap-2"><x-icon name="bag" class="size-5 text-brand-600" /> Pedido</h2>
            @if ($cart)
                <button type="button" wire:click="clearCart" wire:confirm="Limpar o pedido?" class="btn-ghost btn-sm text-red-600 hover:bg-red-50 hover:text-red-700">Limpar</button>
            @endif
        </div>

        <div class="space-y-4 p-5">
            @if ($draftRestored)
                <div class="flex items-start gap-2 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2.5 text-sm text-sky-900">
                    <x-icon name="refresh" class="mt-0.5 size-4 shrink-0" />
                    <span class="flex-1">Recuperamos o pedido que estava em andamento.</span>
                    <button type="button" wire:click="clearCart" class="shrink-0 font-semibold underline">Começar do zero</button>
                </div>
            @endif
            @error('cart') <div class="alert-error"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $message }}</div> @enderror
            @error('cash') <div class="alert-error"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $message }}</div> @enderror

            @include('livewire.tenant.partials.cart-lines')

            <div class="grid grid-cols-3 gap-1 rounded-xl bg-stone-100 p-1 text-sm">
                @foreach ($fulfillments as $value => [$label, $icon])
                    <label @class(['flex cursor-pointer items-center justify-center gap-1.5 rounded-lg px-2 py-2 font-semibold transition', 'bg-white text-brand-700 shadow-sm' => $fulfillment === $value, 'text-stone-500 hover:text-stone-800' => $fulfillment !== $value])>
                        <input type="radio" wire:model.live="fulfillment" value="{{ $value }}" class="sr-only">
                        <x-icon :name="$icon" class="size-4" /> {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <input wire:model="customerName" type="text" class="input" placeholder="Nome {{ $fulfillment === 'delivery' ? '' : '(opcional)' }}" aria-label="Nome do cliente">
                    @error('customer.name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <input wire:model.blur="customerPhone" type="tel" class="input" placeholder="Telefone {{ $fulfillment === 'delivery' ? '' : '(opcional)' }}" aria-label="Telefone do cliente">
                    @error('customer.phone') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
            @if ($customerNotes)
                <p class="flex items-start gap-1.5 rounded-lg bg-amber-50 px-2.5 py-2 text-xs font-medium text-amber-800"><x-icon name="user" class="size-3.5" /> {{ $customerNotes }}</p>
            @endif
            @if ($loyalty)
                {{-- Cartão fidelidade do cliente: quanto falta e prêmio para usar agora. --}}
                <div class="rounded-lg bg-brand-50 px-2.5 py-2 text-xs text-brand-900">
                    <p class="flex items-center gap-1.5 font-semibold"><x-icon name="star" class="size-3.5" /> Fidelidade: {{ $loyalty['stamps'] }} de {{ $loyalty['goal'] }} pedidos</p>
                    @foreach ($loyalty['rewards'] as $reward)
                        <p class="mt-1.5 flex items-center gap-2">
                            <span class="min-w-0 flex-1">Prêmio disponível: <strong class="font-mono">{{ $reward->code }}</strong> · {{ $reward->benefit() }}</span>
                            @if ($coupon !== $reward->code)
                                <button type="button" wire:click="useReward(@js($reward->code))" class="btn-primary btn-sm shrink-0">Usar</button>
                            @endif
                        </p>
                    @endforeach
                </div>
            @endif

            @if ($fulfillment === 'delivery')
                <div class="grid grid-cols-3 gap-2">
                    <input wire:model="address.street" type="text" class="input col-span-2" placeholder="Rua" aria-label="Rua">
                    <input wire:model="address.number" type="text" class="input" placeholder="Nº" aria-label="Número">
                    @if ($zones->isNotEmpty())
                        <select wire:model.live="address.district" class="input col-span-2" aria-label="Bairro">
                            <option value="">Bairro</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone->name }}">{{ $zone->name }} · {{ $zone->fee_cents->format() }}</option>
                            @endforeach
                        </select>
                    @else
                        <input wire:model.blur="address.district" type="text" class="input col-span-2" placeholder="Bairro" aria-label="Bairro">
                    @endif
                    <input wire:model="address.complement" type="text" class="input" placeholder="Compl." aria-label="Complemento">
                    <input wire:model="address.reference" type="text" class="input col-span-3" placeholder="Referência" aria-label="Referência">
                </div>
                @if ($unknownZone)
                    <p class="field-error">Bairro fora da área de entrega.</p>
                @endif
                @foreach (['address.street', 'address.number', 'address.district'] as $field)
                    @error($field) <p class="field-error">{{ $message }}</p> @enderror
                @endforeach
            @endif

            <div class="grid grid-cols-2 gap-2">
                <select wire:model.live="paymentMethod" class="input" aria-label="Forma de pagamento">
                    @foreach ($paymentMethods as $method)
                        <option value="{{ $method->code }}">{{ $method->name }}</option>
                    @endforeach
                </select>
                @if ($paymentMethods->firstWhere('code', $paymentMethod)?->accepts_change)
                    <input wire:model.live.debounce.400ms="changeFor" type="text" inputmode="decimal" class="input" placeholder="Troco para" aria-label="Troco para">
                @endif
            </div>
            {{-- Cupom (o mesmo do cardápio online) ou desconto manual, nunca os dois. --}}
            @if ($appliedCoupon)
                <div @class(['flex items-center gap-3 rounded-xl border-2 border-dashed px-3 py-2', 'border-emerald-300 bg-emerald-50' => ! $couponProblem, 'border-amber-300 bg-amber-50' => $couponProblem])>
                    <x-icon name="ticket" @class(['size-5 shrink-0', 'text-emerald-700' => ! $couponProblem, 'text-amber-700' => $couponProblem]) />
                    <span class="min-w-0 flex-1 text-sm">
                        <span class="block font-bold tracking-wide">{{ $appliedCoupon->code }}</span>
                        <span @class(['block text-xs', 'text-emerald-800' => ! $couponProblem, 'font-medium text-amber-800' => $couponProblem])>{{ $couponProblem ?: $appliedCoupon->benefit() }}</span>
                    </span>
                    <button type="button" wire:click="removeCoupon" class="btn-ghost btn-sm text-stone-500">Remover</button>
                </div>
            @else
                <div class="flex gap-2">
                    <input wire:model="couponInput" wire:keydown.enter.prevent="applyCoupon" type="text" class="input min-w-0 flex-1 uppercase" placeholder="Cupom de desconto" aria-label="Cupom de desconto" autocomplete="off">
                    <button type="button" wire:click="applyCoupon" class="btn-secondary shrink-0">Aplicar</button>
                </div>
                @if ($canDiscount)
                    <input wire:model.live.debounce.400ms="discount" type="text" inputmode="decimal" class="input" placeholder="Desconto manual (R$)" aria-label="Desconto">
                @endif
            @endif
            @error('coupon') <p class="field-error">{{ $message }}</p> @enderror
            @error('payment_method') <p class="field-error">{{ $message }}</p> @enderror
            @error('change_for') <p class="field-error">{{ $message }}</p> @enderror
            @error('changeFor') <p class="field-error">{{ $message }}</p> @enderror
            @error('discount') <p class="field-error">{{ $message }}</p> @enderror

            <input wire:model="notes" type="text" class="input" placeholder="Observações do pedido" aria-label="Observações">
        </div>

        <div class="space-y-1.5 border-t border-stone-100 bg-stone-50/70 p-5 text-sm">
            <div class="flex justify-between text-stone-600"><span>Subtotal</span><span class="tabular-nums">{{ $subtotal->format() }}</span></div>
            @if (! $couponDiscount->isZero())
                <div class="flex justify-between text-emerald-700"><span>Cupom {{ $appliedCoupon->code }}</span><span class="tabular-nums">- {{ $couponDiscount->format() }}</span></div>
            @elseif (! $appliedCoupon && $discount !== '' && \App\Support\Money::isValid($discount) && ! \App\Support\Money::parse($discount)->isZero())
                <div class="flex justify-between text-emerald-700"><span>Desconto</span><span class="tabular-nums">- {{ \App\Support\Money::parse($discount)->format() }}</span></div>
            @endif
            @if ($fulfillment === 'delivery')
                <div class="flex justify-between text-stone-600"><span>Entrega</span><span class="tabular-nums">{{ $fee->format() }}</span></div>
            @endif
            <div class="flex items-baseline justify-between pt-1"><span class="font-semibold">Total</span><span class="text-2xl font-extrabold tracking-tight tabular-nums">{{ $total->format() }}</span></div>
            <p class="text-xs text-stone-500">{{ $fulfillment === 'counter' ? 'Balcão: o pagamento entra no seu caixa agora.' : 'Pagamento recebido na entrega/retirada.' }}</p>

            <button type="submit" class="btn-primary btn-lg mt-3 w-full" @disabled(! $cart) wire:loading.attr="disabled">
                <x-icon name="check" class="size-5" /> Finalizar pedido
            </button>
        </div>
    </form>

    @include('livewire.tenant.partials.addon-modal')
</div>
