<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('stock.index') }}" class="btn-link" wire:navigate>← Estoque</a>
            <h1 class="page-title mt-1">Fichas técnicas</h1>
            <p class="text-sm text-stone-500">{{ $withRecipe }} de {{ $products->count() }} produtos com ficha. Sem ficha, a venda não baixa estoque.</p>
        </div>
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead class="bg-stone-50"><tr><th>Produto</th><th class="text-right">Preço</th><th class="text-right">Custo (ficha)</th><th class="text-right">Margem</th><th></th></tr></thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($products as $row)
                    <tr wire:key="p-{{ $row['product']->id }}">
                        <td>
                            <p class="font-medium text-stone-900">{{ $row['product']->name }}</p>
                            <p class="text-xs text-stone-500">{{ $row['product']->category->name }}</p>
                        </td>
                        <td class="text-right whitespace-nowrap">{{ $row['product']->price_cents->format() }}</td>
                        <td class="text-right whitespace-nowrap">{{ $row['cost']?->format() ?? '—' }}</td>
                        <td @class(['text-right whitespace-nowrap font-medium', 'text-red-700' => $row['margin'] !== null && $row['margin'] < 50, 'text-green-700' => $row['margin'] !== null && $row['margin'] >= 65])>
                            {{ $row['margin'] !== null ? number_format($row['margin'], 1, ',', '.').'%' : '—' }}
                        </td>
                        <td class="text-right"><a href="{{ route('stock.recipe.product', $row['product']) }}" class="btn-link" wire:navigate>{{ $row['recipe'] ? 'Editar ficha' : 'Criar ficha' }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <section class="card card-body">
        <h2 class="font-semibold">Adicionais</h2>
        <p class="hint">Ex.: "Bacon extra" consome 50 g de bacon. Opções sem ficha não baixam estoque.</p>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            @foreach ($groups as $group)
                <div>
                    <p class="text-sm font-semibold">{{ $group->name }}</p>
                    <ul class="mt-1 space-y-1 text-sm">
                        @foreach ($group->addons as $addon)
                            <li class="flex justify-between gap-2" wire:key="a-{{ $addon->id }}">
                                <span>{{ $addon->name }} <span class="text-xs text-stone-500">{{ isset($addonCosts[$addon->id]) ? '· custo '.$addonCosts[$addon->id]->format() : '' }}</span></span>
                                <a href="{{ route('stock.recipe.addon', $addon) }}" class="btn-link text-xs" wire:navigate>{{ isset($addonCosts[$addon->id]) ? 'Editar' : 'Ficha' }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>
</div>
