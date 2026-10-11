{{-- Resultados da busca do cardápio (dentro do Alpine menuApp): combos e produtos que batem com o termo. --}}
<div class="space-y-3">
    <p class="text-sm text-stone-500" x-show="results.length || comboResults.length"
       x-text="(results.length + comboResults.length) + ((results.length + comboResults.length) === 1 ? ' resultado' : ' resultados') + ' para “' + term.trim() + '”'"></p>
    <template x-for="combo in comboResults" :key="'c' + combo.id">
        <div class="card flex items-center gap-3 p-3">
            <template x-if="combo.image"><img :src="combo.image" alt="" class="size-20 rounded-2xl object-cover"></template>
            <div class="min-w-0 flex-1">
                <p class="font-semibold" x-text="combo.name"></p>
                <p class="truncate text-xs text-stone-500" x-text="combo.items"></p>
                <p class="mt-1 text-sm font-bold" x-text="money(combo.price)"></p>
            </div>
            <button type="button" x-show="ordering" class="btn-icon btn-primary rounded-full" aria-label="Adicionar combo"
                    @click="$store.cart.add({ type: 'combo', id: combo.id, name: combo.name, unit: combo.price, addons: [], quantity: 1, notes: '' })">
                <x-icon name="plus" class="size-5" />
            </button>
        </div>
    </template>
    <template x-for="product in results" :key="product.id">
        <button type="button" @click="$dispatch('open-product', product.id)" class="card flex w-full items-center gap-3 p-3 text-left active:scale-[0.99]">
            <template x-if="product.images.length"><img :src="product.images[0]" alt="" class="size-20 rounded-2xl object-cover" :class="! product.available && 'grayscale'"></template>
            <template x-if="! product.images.length">
                <span class="flex size-20 items-center justify-center rounded-2xl bg-stone-100 text-stone-400"><x-icon name="utensils" class="size-6" /></span>
            </template>
            <span class="min-w-0 flex-1">
                <span class="block truncate font-semibold" x-text="product.name"></span>
                <span class="block text-xs text-stone-500" x-text="product.category"></span>
                <span class="mt-1 flex flex-wrap items-center gap-2 text-sm font-bold">
                    <span x-text="money(product.price)"></span>
                    <span class="badge-gray" x-show="! product.available">Esgotado</span>
                    <span class="badge-brand" x-show="$store.cart.qty(product.id)" x-text="$store.cart.qty(product.id) + ' no carrinho'"></span>
                </span>
            </span>
            <x-icon name="chevron-right" class="size-5 text-stone-400" />
        </button>
    </template>
    <div class="card" x-show="! results.length && ! comboResults.length">
        <x-ui.empty icon="search" title="Nada encontrado" text="Tente outro nome ou veja as categorias do cardápio." />
    </div>
</div>
