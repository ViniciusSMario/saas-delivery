<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="page-title">Produtos</h1>
            <p class="mt-1 text-sm text-stone-500">"Esgotado" mantém o produto no cardápio, sem permitir pedido.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ url('/cardapio') }}" target="_blank" class="btn-secondary">Ver cardápio</a>
            @if ($categories->isNotEmpty())
                <a href="{{ route('menu.products.create') }}" class="btn-primary" wire:navigate>Novo produto</a>
            @endif
        </div>
    </div>

    @if ($categories->isEmpty())
        <div class="alert-error">Crie uma categoria antes de cadastrar produtos. <a href="{{ route('menu.categories') }}" class="font-medium underline" wire:navigate>Ir para categorias</a></div>
    @endif

    <div class="flex flex-wrap gap-3">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar por nome ou código" class="input max-w-xs" aria-label="Buscar">
        <select wire:model.live="categoryId" class="input w-auto" aria-label="Categoria">
            <option value="">Todas as categorias</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="status" class="input w-auto" aria-label="Situação">
            <option value="">Todas as situações</option>
            <option value="active">Ativos</option>
            <option value="inactive">Inativos</option>
            <option value="sold_out">Esgotados</option>
        </select>
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead class="bg-stone-50">
                <tr><th>Produto</th><th>Categoria</th><th class="text-right">Preço</th><th class="text-right">Ações</th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($products as $product)
                    <tr wire:key="product-{{ $product->id }}">
                        <td>
                            <div class="flex items-center gap-3">
                                @if ($product->cover)
                                    <img src="{{ route('menu.images.show', $product->cover) }}" alt="" class="size-10 rounded-lg object-cover" loading="lazy">
                                @else
                                    <div class="size-10 rounded-lg bg-stone-100"></div>
                                @endif
                                <div>
                                    <p class="font-medium text-stone-900">
                                        @if ($product->code)<span class="mr-1 font-mono text-xs text-stone-500">{{ $product->code }}</span>@endif
                                        {{ $product->name }}
                                    </p>
                                    <p class="space-x-1">
                                        @unless ($product->is_active)<span class="badge bg-stone-200 text-stone-600">inativo</span>@endunless
                                        @unless ($product->is_available)<span class="badge bg-amber-100 text-amber-800">esgotado</span>@endunless
                                        @if ($product->is_featured)<span class="badge bg-brand-100 text-brand-700">destaque</span>@endif
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="text-stone-500">{{ $product->category->name }}</td>
                        <td class="text-right whitespace-nowrap">{{ $product->price_cents->format() }}</td>
                        <td class="text-right whitespace-nowrap">
                            <button type="button" wire:click="toggleAvailability({{ $product->id }})" class="btn-link">{{ $product->is_available ? 'Marcar esgotado' : 'Voltou' }}</button>
                            <a href="{{ route('menu.products.edit', $product) }}" class="btn-link ml-3" wire:navigate>Editar</a>
                            <button type="button" wire:click="delete({{ $product->id }})" wire:confirm="Excluir {{ $product->name }}? Ele sai do cardápio; pedidos antigos não são afetados." class="btn-link ml-3 text-red-700">Excluir</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-stone-500">Nenhum produto encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $products->links() }}
</div>
