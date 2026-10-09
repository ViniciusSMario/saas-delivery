{{-- Busca + grade de produtos/combos. Usada no PDV e na comanda. Espera: $categories, $products, $combos, $categoryId. --}}
<div class="space-y-4" x-data>
    <input wire:model.live.debounce.250ms="search" wire:keydown.enter.prevent="addFirstResult" x-ref="search" type="search" class="input"
           placeholder="Buscar por nome ou código · Enter adiciona · / para focar" autofocus aria-label="Buscar produto"
           x-init="window.addEventListener('keydown', (e) => { if (e.key === '/' && ! ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) { e.preventDefault(); $el.focus(); } })">

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
