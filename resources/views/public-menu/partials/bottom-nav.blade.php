{{--
    Navegação inferior do app do cardápio. No cardápio ($current = null) a aba ativa vem
    do Alpine (menuApp.tab); nas demais páginas é fixa. Os links usam o #hash da aba.
--}}
@php
    $tabs = [
        ['id' => 'cardapio', 'label' => 'Cardápio', 'icon' => 'utensils'],
        ['id' => 'buscar', 'label' => 'Buscar', 'icon' => 'search'],
        ($tableMode ?? false) ? ['id' => 'pedidos', 'label' => 'Conta', 'icon' => 'file'] : ['id' => 'pedidos', 'label' => 'Pedidos', 'icon' => 'bag'],
        ['id' => 'loja', 'label' => 'Loja', 'icon' => 'store'],
    ];
@endphp
<nav class="fixed inset-x-0 bottom-0 z-30 mx-auto max-w-xl border-t border-stone-200/80 bg-white/95 backdrop-blur-lg pb-safe" aria-label="Navegação do cardápio">
    <ul class="grid grid-cols-4">
        @foreach ($tabs as $tab)
            <li>
                <a href="{{ $menuUrl }}#{{ $tab['id'] }}"
                   @if ($current === null)
                       :class="tab === '{{ $tab['id'] }}' ? 'text-brand-600' : 'text-stone-500'"
                       :aria-current="tab === '{{ $tab['id'] }}' ? 'page' : null"
                       @click="reselect('{{ $tab['id'] }}', $event)"
                   @endif
                   @class([
                       'relative flex flex-col items-center gap-0.5 pt-2.5 pb-1.5 text-[11px] font-semibold transition active:scale-95',
                       'text-brand-600' => $current === $tab['id'],
                       'text-stone-500' => $current !== null && $current !== $tab['id'],
                   ])>
                    <x-icon :name="$tab['icon']" class="size-6" />
                    {{ $tab['label'] }}
                    @if ($tab['id'] === 'pedidos' && ! ($tableMode ?? false))
                        <span x-data x-cloak x-show="$store.orders?.active > 0" class="absolute top-1.5 left-1/2 ml-2 size-2.5 rounded-full bg-brand-600 ring-2 ring-white"></span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</nav>
