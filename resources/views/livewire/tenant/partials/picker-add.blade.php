{{-- Botão "+" do cartão de produto; mostra a quantidade quando o item já está no carrinho. Espera: $qty. --}}
@if ($qty)
    <span class="flex h-8 min-w-8 items-center justify-center rounded-full bg-brand-600 px-2 text-sm font-bold text-white">{{ $qty }}</span>
@else
    <span class="flex size-8 items-center justify-center rounded-full bg-stone-100 text-stone-500 transition group-hover:bg-brand-600 group-hover:text-white"><x-icon name="plus" class="size-4" stroke="2.4" /></span>
@endif
