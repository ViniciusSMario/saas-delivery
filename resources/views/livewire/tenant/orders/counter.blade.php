<div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
    {{-- Produtos --}}
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="page-title">Venda no balcão</h1>
            <a href="{{ route('orders.board') }}" class="btn-secondary" wire:navigate>Painel</a>
        </div>

        <input wire:model.live.debounce.250ms="search" type="search" class="input" placeholder="Buscar por nome ou código (ex.: 101)" autofocus aria-label="Buscar produto">

        <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
            <button type="button" wire:click="$set('categoryId', null)" @class(['shrink-0 rounded-full border px-3 py-1 text-sm', 'border-brand-600 bg-brand-50 text-brand-700' => ! $categoryId, 'border-stone-300' => $categoryId])>Tudo</button>
            @foreach ($categories as $category)
                <button type="button" wire:click="$set('categoryId', {{ $category->id }})" @class(['shrink-0 rounded-full border px-3 py-1 text-sm whitespace-nowrap', 'border-brand-600 bg-brand-50 text-brand-700' => $categoryId === $category->id, 'border-stone-300' => $categoryId !== $category->id])>{{ $category->name }}</button>
            @endforeach
        </div>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
            @foreach ($combos as $combo)
                <button type="button" wire:key="c-{{ $combo->id }}" wire:click="addCombo({{ $combo->id }})" class="card p-3 text-left hover:border-brand-500">
                    <span class="badge bg-brand-100 text-brand-700">combo</span>
                    <span class="mt-1 block text-sm font-medium">{{ $combo->name }}</span>
                    <span class="block text-sm text-stone-600">{{ $combo->price_cents->format() }}</span>
                </button>
            @endforeach
            @forelse ($products as $product)
                <button type="button" wire:key="p-{{ $product->id }}" wire:click="addProduct({{ $product->id }})" class="card p-3 text-left hover:border-brand-500">
                    @if ($product->code)<span class="font-mono text-xs text-stone-500">{{ $product->code }}</span>@endif
                    <span class="block text-sm font-medium">{{ $product->name }}</span>
                    <span class="block text-sm text-stone-600">{{ $product->price_cents->format() }}{{ $product->addonGroups->isNotEmpty() ? ' +' : '' }}</span>
                </button>
            @empty
                <p class="col-span-full py-6 text-center text-sm text-stone-500">Nenhum produto disponível encontrado.</p>
            @endforelse
        </div>
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

        <ul class="max-h-72 divide-y divide-stone-100 overflow-y-auto text-sm">
            @forelse ($cart as $line)
                <li wire:key="line-{{ $line['key'] }}" class="flex gap-2 py-2">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ $line['name'] }}</p>
                        @if ($line['addons'])<p class="text-xs text-stone-500">{{ implode(', ', array_column($line['addons'], 'name')) }}</p>@endif
                        @if ($line['notes'])<p class="text-xs text-amber-700">Obs.: {{ $line['notes'] }}</p>@endif
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" wire:click="setQuantity('{{ $line['key'] }}', {{ $line['quantity'] - 1 }})" class="btn-secondary px-2 py-0.5">−</button>
                        <span class="w-6 text-center">{{ $line['quantity'] }}</span>
                        <button type="button" wire:click="setQuantity('{{ $line['key'] }}', {{ $line['quantity'] + 1 }})" class="btn-secondary px-2 py-0.5">+</button>
                    </div>
                    <p class="w-20 text-right whitespace-nowrap">{{ \App\Support\Money::cents(($line['unit'] + array_sum(array_column($line['addons'], 'price'))) * $line['quantity'])->format() }}</p>
                </li>
            @empty
                <li class="py-6 text-center text-stone-500">Toque nos produtos para adicionar.</li>
            @endforelse
        </ul>

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
            <p class="text-xs text-stone-500">{{ $fulfillment === 'counter' ? 'Balcão: o pagamento é registrado agora.' : 'Pagamento recebido na entrega/retirada.' }}</p>
        </div>

        <button type="submit" class="btn-primary w-full py-3" @disabled(! $cart) wire:loading.attr="disabled">Finalizar pedido</button>
    </form>

    {{-- Escolha de adicionais --}}
    @if ($picking)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-stone-900/40 p-4 sm:items-center" wire:click.self="$set('pickingId', null)">
            <div class="card max-h-[90vh] w-full max-w-md overflow-y-auto p-5">
                <h3 class="text-lg font-semibold">{{ $picking->name }}</h3>
                <p class="text-sm text-stone-500">{{ $picking->price_cents->format() }}</p>
                @error('picked') <div class="alert-error mt-3">{{ $message }}</div> @enderror

                @foreach ($picking->addonGroups as $group)
                    @continue($group->addons->isEmpty())
                    <div class="mt-4">
                        <p class="text-sm font-semibold">{{ $group->name }} <span class="text-xs font-normal text-stone-500">{{ $group->rule() }}{{ $group->isRequired() ? ' · obrigatório' : '' }}</span></p>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            @foreach ($group->addons as $addon)
                                @php($on = in_array($addon->id, $picked[$group->id] ?? [], true))
                                <button type="button" wire:click="togglePick({{ $group->id }}, {{ $addon->id }})"
                                        @class(['rounded-lg border px-2 py-1.5 text-left text-sm', 'border-brand-600 bg-brand-50' => $on, 'border-stone-300' => ! $on])>
                                    {{ $addon->name }}
                                    @unless ($addon->price_cents->isZero())<span class="block text-xs text-stone-500">+ {{ $addon->price_cents->format() }}</span>@endunless
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="mt-4 grid grid-cols-[6rem_1fr] gap-2">
                    <input wire:model="pickQuantity" type="number" min="1" max="99" class="input" aria-label="Quantidade">
                    <input wire:model="pickNotes" type="text" class="input" placeholder="Observação (ex.: sem cebola)" aria-label="Observação do item">
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="$set('pickingId', null)" class="btn-secondary">Cancelar</button>
                    <button type="button" wire:click="confirmPick" class="btn-primary">Adicionar</button>
                </div>
            </div>
        </div>
    @endif
</div>
