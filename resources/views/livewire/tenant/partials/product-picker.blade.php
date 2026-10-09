{{-- Busca + grade de produtos/combos. Usada no PDV e na comanda. Espera: $categories, $products, $combos, $categoryId. --}}
<div class="space-y-4" x-data>
    <label class="relative block">
        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-stone-400" />
        <input wire:model.live.debounce.250ms="search" wire:keydown.enter.prevent="addFirstResult" x-ref="search" type="search" class="input h-12 rounded-2xl pl-11 text-base"
               placeholder="Buscar por nome ou código · Enter adiciona" autofocus aria-label="Buscar produto"
               x-init="window.addEventListener('keydown', (e) => { if (e.key === '/' && ! ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) { e.preventDefault(); $el.focus(); } })">
        <kbd class="pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 rounded-md border border-stone-200 bg-stone-50 px-1.5 text-xs text-stone-500 sm:block">/</kbd>
    </label>

    <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 scrollbar-none">
        <button type="button" wire:click="$set('categoryId', null)" @class(['chip', 'chip-active' => ! $categoryId])>Tudo</button>
        @foreach ($categories as $category)
            <button type="button" wire:click="$set('categoryId', {{ $category->id }})" @class(['chip', 'chip-active' => $categoryId === $category->id])>{{ $category->name }}</button>
        @endforeach
    </div>

    <div class="grid grid-cols-[repeat(auto-fill,minmax(10rem,1fr))] gap-3">
        @foreach ($combos as $combo)
            <button type="button" wire:key="c-{{ $combo->id }}" wire:click="addCombo({{ $combo->id }})"
                    class="card group flex min-h-28 flex-col justify-between p-3.5 text-left transition hover:border-brand-400 hover:shadow-md active:scale-[0.98]">
                <span>
                    <span class="badge-brand"><x-icon name="gift" class="size-3" /> combo</span>
                    <span class="mt-1.5 block text-sm leading-snug font-semibold">{{ $combo->name }}</span>
                </span>
                <span class="mt-2 flex items-center justify-between">
                    <span class="text-sm font-bold text-brand-700">{{ $combo->price_cents->format() }}</span>
                    <span class="flex size-7 items-center justify-center rounded-full bg-brand-50 text-brand-600 transition group-hover:bg-brand-600 group-hover:text-white"><x-icon name="plus" class="size-4" stroke="2.4" /></span>
                </span>
            </button>
        @endforeach
        @forelse ($products as $product)
            <button type="button" wire:key="p-{{ $product->id }}" wire:click="addProduct({{ $product->id }})"
                    class="card group flex min-h-28 flex-col justify-between p-3.5 text-left transition hover:border-brand-400 hover:shadow-md active:scale-[0.98]">
                <span>
                    @if ($product->code)<span class="font-mono text-[11px] text-stone-400">{{ $product->code }}</span>@endif
                    <span class="block text-sm leading-snug font-semibold">{{ $product->name }}</span>
                </span>
                <span class="mt-2 flex items-center justify-between">
                    <span class="text-sm font-bold text-stone-800">{{ $product->price_cents->format() }}@if ($product->addonGroups->isNotEmpty())<span class="font-normal text-stone-400"> +</span>@endif</span>
                    <span class="flex size-7 items-center justify-center rounded-full bg-stone-100 text-stone-500 transition group-hover:bg-brand-600 group-hover:text-white"><x-icon name="plus" class="size-4" stroke="2.4" /></span>
                </span>
            </button>
        @empty
            <div class="card col-span-full">
                <x-ui.empty icon="search" title="Nenhum produto encontrado" text="Confira a busca ou escolha outra categoria." />
            </div>
        @endforelse
    </div>
</div>
