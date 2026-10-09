<div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
    {{-- Produtos --}}
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="page-title">PDV</h1>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('cash.current') }}" class="btn-secondary" wire:navigate>Caixa</a>
                <a href="{{ route('orders.board') }}" class="btn-secondary" wire:navigate>Painel</a>
            </div>
        </div>

        @if (! $session)
            <div class="alert-error">
                Seu caixa está fechado: vendas no balcão (pagas na hora) precisam de caixa aberto.
                <a href="{{ route('cash.current') }}" class="font-medium underline" wire:navigate>Abrir caixa</a>
            </div>
        @endif

        @if ($awaiting->isNotEmpty())
            <div class="card card-body">
                <p class="text-sm font-semibold">Contas pedidas aguardando pagamento</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($awaiting as $command)
                        <a href="{{ route('tables.command', $command) }}" class="btn-secondary" wire:navigate wire:key="aw-{{ $command->id }}">
                            {{ $command->displayName() }} · #{{ $command->number }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @include('livewire.tenant.partials.product-picker')
    </div>

    {{-- Carrinho e fechamento --}}
    <form wire:submit="checkout" class="card card-body h-fit space-y-4 lg:sticky lg:top-4">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold">Pedido</h2>
            @if ($cart)
                <button type="button" wire:click="clearCart" wire:confirm="Limpar o pedido?" class="btn-link text-xs text-red-700">Limpar</button>
            @endif
        </div>

        @error('cart') <div class="alert-error">{{ $message }}</div> @enderror
        @error('cash') <div class="alert-error">{{ $message }}</div> @enderror

        @include('livewire.tenant.partials.cart-lines')

        <div class="grid grid-cols-3 gap-2 text-sm">
            @foreach (['counter' => 'Balcão', 'pickup' => 'Retirada', 'delivery' => 'Entrega'] as $value => $label)
                <label @class(['cursor-pointer rounded-lg border px-2 py-1.5 text-center', 'border-brand-600 bg-brand-50 text-brand-700' => $fulfillment === $value, 'border-stone-300' => $fulfillment !== $value])>
                    <input type="radio" wire:model.live="fulfillment" value="{{ $value }}" class="sr-only"> {{ $label }}
                </label>
            @endforeach
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <input wire:model="customerName" type="text" class="input" placeholder="Nome {{ $fulfillment === 'delivery' ? '' : '(opcional)' }}" aria-label="Nome do cliente">
                @error('customer.name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <input wire:model="customerPhone" type="tel" class="input" placeholder="Telefone {{ $fulfillment === 'delivery' ? '' : '(opcional)' }}" aria-label="Telefone do cliente">
                @error('customer.phone') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        @if ($fulfillment === 'delivery')
            <div class="grid grid-cols-3 gap-2">
                <input wire:model="address.street" type="text" class="input col-span-2" placeholder="Rua" aria-label="Rua">
                <input wire:model="address.number" type="text" class="input" placeholder="Nº" aria-label="Número">
                <input wire:model="address.district" type="text" class="input col-span-2" placeholder="Bairro" aria-label="Bairro">
                <input wire:model="address.complement" type="text" class="input" placeholder="Compl." aria-label="Complemento">
                <input wire:model="address.reference" type="text" class="input col-span-3" placeholder="Referência" aria-label="Referência">
            </div>
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
        @error('payment_method') <p class="field-error">{{ $message }}</p> @enderror
        @error('change_for') <p class="field-error">{{ $message }}</p> @enderror
        @error('changeFor') <p class="field-error">{{ $message }}</p> @enderror

        @if ($canDiscount)
            <div>
                <input wire:model.live.debounce.400ms="discount" type="text" inputmode="decimal" class="input" placeholder="Desconto (R$)" aria-label="Desconto">
                @error('discount') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        @endif

        <input wire:model="notes" type="text" class="input" placeholder="Observações do pedido" aria-label="Observações">

        <div class="space-y-1 border-t border-stone-100 pt-3 text-sm">
            <div class="flex justify-between"><span>Subtotal</span><span>{{ $subtotal->format() }}</span></div>
            @if ($discount !== '' && \App\Support\Money::isValid($discount) && ! \App\Support\Money::parse($discount)->isZero())
                <div class="flex justify-between text-green-700"><span>Desconto</span><span>- {{ \App\Support\Money::parse($discount)->format() }}</span></div>
            @endif
            @if ($fulfillment === 'delivery')
                <div class="flex justify-between"><span>Entrega</span><span>{{ $fee->format() }}</span></div>
            @endif
            <div class="flex justify-between text-lg font-semibold"><span>Total</span><span>{{ $total->format() }}</span></div>
            <p class="text-xs text-stone-500">{{ $fulfillment === 'counter' ? 'Balcão: o pagamento entra no seu caixa agora.' : 'Pagamento recebido na entrega/retirada.' }}</p>
        </div>

        <button type="submit" class="btn-primary w-full py-3" @disabled(! $cart) wire:loading.attr="disabled">Finalizar pedido</button>
    </form>

    @include('livewire.tenant.partials.addon-modal')
</div>
