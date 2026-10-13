{{-- Busca + grade de produtos/combos. Usada no PDV e na comanda. Espera: $categories, $products, $combos, $categoryId, $cart. --}}
@php
    // Quantos de cada item já estão no carrinho: aparece no cartão do produto.
    $inCart = collect($cart ?? [])->groupBy(fn ($line) => $line['type'].'-'.$line['id'])->map(fn ($lines) => $lines->sum('quantity'));
@endphp
<div class="min-w-0 space-y-3 sm:space-y-4" x-data>
    <label class="relative block">
        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-stone-400" />
        {{-- Foco automático só com mouse/teclado: no celular abriria o teclado por cima dos produtos. --}}
        <input wire:model.live.debounce.250ms="search" wire:keydown.enter.prevent="addFirstResult" wire:keydown.escape="$set('search', '')" x-ref="search" type="search" class="input h-12 rounded-2xl pl-11 text-base"
               placeholder="Buscar por nome ou código" aria-label="Buscar produto" enterkeyhint="search"
               data-hotkey="/ F3" data-hotkey-label="Buscar produto"
               x-init="if (matchMedia('(pointer: fine)').matches) $el.focus()">
        <kbd class="kbd pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-stone-500">/</kbd>
    </label>
    <template data-hotkey-help="enter">Na busca: adicionar o primeiro produto</template>
    <template data-hotkey-help="esc">Na busca: limpar</template>

    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 scrollbar-none sm:-mx-1 sm:px-1">
        <button type="button" wire:click="$set('categoryId', null)" @class(['chip h-10', 'chip-active' => ! $categoryId])>Tudo</button>
        @foreach ($categories as $category)
            <button type="button" wire:click="$set('categoryId', {{ $category->id }})" @class(['chip h-10', 'chip-active' => $categoryId === $category->id])>{{ $category->name }}</button>
        @endforeach
    </div>

    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-[repeat(auto-fill,minmax(10rem,1fr))] sm:gap-3">
        @foreach ($combos as $combo)
            @php($qty = $inCart['combo-'.$combo->id] ?? 0)
            <button type="button" wire:key="c-{{ $combo->id }}" wire:click="addCombo({{ $combo->id }})" data-busy="off"
                    @class(['card group relative flex min-h-28 flex-col justify-between p-3.5 text-left transition hover:border-brand-400 hover:shadow-md active:scale-[0.97]', 'border-brand-500 ring-2 ring-brand-500' => $qty])>
                <span class="w-full">
                    <span class="badge-brand"><x-icon name="gift" class="size-3" /> combo</span>
                    <span class="mt-1.5 block text-sm leading-snug font-semibold">{{ $combo->name }}</span>
                </span>
                <span class="mt-2 flex w-full items-center justify-between">
                    <span class="text-sm font-bold text-brand-700">{{ $combo->price_cents->format() }}</span>
                    @include('livewire.tenant.partials.picker-add', ['qty' => $qty])
                </span>
            </button>
        @endforeach
        @forelse ($products as $product)
            @php($qty = $inCart['product-'.$product->id] ?? 0)
            <button type="button" wire:key="p-{{ $product->id }}" wire:click="addProduct({{ $product->id }})" data-busy="off"
                    @class(['card group relative flex min-h-28 flex-col justify-between p-3.5 text-left transition hover:border-brand-400 hover:shadow-md active:scale-[0.97]', 'border-brand-500 ring-2 ring-brand-500' => $qty])>
                <span class="w-full">
                    @if ($product->code)<span class="font-mono text-[11px] text-stone-400">{{ $product->code }}</span>@endif
                    <span class="block text-sm leading-snug font-semibold">{{ $product->name }}</span>
                </span>
                <span class="mt-2 flex w-full items-center justify-between">
                    <span class="text-sm font-bold text-stone-800">{{ $product->price_cents->format() }}@if ($product->addonGroups->isNotEmpty())<span class="font-normal text-stone-400"> +</span>@endif</span>
                    @include('livewire.tenant.partials.picker-add', ['qty' => $qty])
                </span>
            </button>
        @empty
            <div class="card col-span-full">
                <x-ui.empty icon="search" title="Nenhum produto encontrado" text="Confira a busca ou escolha outra categoria." />
            </div>
        @endforelse
    </div>
</div>
