<div class="space-y-6">
    <x-ui.page-header title="Produtos" subtitle="“Esgotado” mantém o produto no cardápio, sem permitir pedido.">
        <a href="{{ url('/cardapio') }}" target="_blank" class="btn-secondary"><x-icon name="external" class="size-4" /> Ver cardápio</a>
        <a href="{{ route('menu.import') }}" class="btn-secondary" wire:navigate><x-icon name="file" class="size-4" /> Importar planilha</a>
        @if ($categories->isNotEmpty())
            <a href="{{ route('menu.products.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> Novo produto</a>
        @endif
    </x-ui.page-header>

    @if ($categories->isEmpty())
        <div class="alert-warning">
            <x-icon name="alert" class="mt-0.5 size-4" />
            <span>Crie uma categoria antes de cadastrar produtos. <a href="{{ route('menu.categories') }}" class="font-semibold underline" wire:navigate>Ir para categorias</a>
                ou <a href="{{ route('menu.import') }}" class="font-semibold underline" wire:navigate>importe o cardápio de uma planilha</a> (as categorias são criadas sozinhas).</span>
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="flex flex-wrap gap-2 border-b border-stone-100 p-3">
            <label class="relative min-w-56 flex-1">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar por nome ou código" class="input pl-9" aria-label="Buscar">
            </label>
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

        <div class="overflow-x-auto">
            <table class="table table-cards">
                <thead>
                    <tr><th>Produto</th><th>Categoria</th><th class="text-right">Preço</th><th class="text-right">Ações</th></tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr wire:key="product-{{ $product->id }}">
                            <td>
                                <div class="flex items-center gap-3">
                                    @if ($product->cover)
                                        <img src="{{ route('menu.images.show', $product->cover) }}" alt="" class="size-11 rounded-xl object-cover" loading="lazy">
                                    @else
                                        <div class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-400"><x-icon name="utensils" class="size-5" /></div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="font-semibold text-stone-900">
                                            @if ($product->code)<span class="mr-1 font-mono text-xs font-normal text-stone-400">{{ $product->code }}</span>@endif
                                            {{ $product->name }}
                                        </p>
                                        <p class="mt-0.5 flex flex-wrap gap-1">
                                            @unless ($product->is_active)<span class="badge-gray">inativo</span>@endunless
                                            @unless ($product->is_available)<span class="badge-amber">esgotado</span>@endunless
                                            @if ($product->is_featured)<span class="badge-brand">destaque</span>@endif
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="text-stone-500">{{ $product->category->name }}</td>
                            <td class="text-right font-semibold whitespace-nowrap tabular-nums">{{ $product->price_cents->format() }}</td>
                            <td class="text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1">
                                    <button type="button" wire:click="toggleAvailability({{ $product->id }})" @class(['btn-sm', 'btn-ghost text-amber-700 hover:bg-amber-50' => $product->is_available, 'btn-secondary' => ! $product->is_available])>
                                        {{ $product->is_available ? 'Marcar esgotado' : 'Voltou' }}
                                    </button>
                                    <a href="{{ route('menu.products.edit', $product) }}" class="btn-icon btn-ghost size-8" wire:navigate title="Editar" aria-label="Editar {{ $product->name }}"><x-icon name="edit" class="size-4" /></a>
                                    <button type="button" wire:click="delete({{ $product->id }})" wire:confirm="Excluir {{ $product->name }}? Ele sai do cardápio; pedidos antigos não são afetados." class="btn-icon btn-ghost size-8 text-red-600 hover:bg-red-50 hover:text-red-700" title="Excluir" aria-label="Excluir {{ $product->name }}"><x-icon name="trash" class="size-4" /></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">
                            @if ($search !== '' || $categoryId || $status !== '')
                                <x-ui.empty icon="search" title="Nenhum produto encontrado" text="Nada com essa busca ou filtro.">
                                    <button type="button" wire:click="clearFilters" class="btn-secondary">Limpar filtros</button>
                                </x-ui.empty>
                            @else
                                <x-ui.empty icon="book" title="Seu cardápio ainda está vazio" text="Cadastre um produto de cada vez ou traga tudo de uma vez pela planilha (Excel ou CSV), com categorias, preços e adicionais.">
                                    @if ($categories->isEmpty())
                                        <a href="{{ route('menu.categories') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> Criar categoria</a>
                                    @else
                                        <a href="{{ route('menu.products.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> Novo produto</a>
                                    @endif
                                    <a href="{{ route('menu.import') }}" class="btn-secondary" wire:navigate><x-icon name="file" class="size-4" /> Importar planilha</a>
                                    @can('establishment.manage')
                                        <a href="{{ route('onboarding', ['step' => 'menu']) }}" class="btn-ghost" wire:navigate>Usar um modelo pronto</a>
                                    @endcan
                                </x-ui.empty>
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $products->links() }}
</div>
