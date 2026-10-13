<div class="space-y-6">
    <div>
        <h1 class="page-title">Categorias</h1>
        <p class="mt-1 text-sm text-stone-500">A ordem aqui é a ordem do cardápio. Categoria inativa some do cardápio público.</p>
    </div>

    <form wire:submit="save" class="card card-body grid gap-4 sm:grid-cols-[1fr_1.5fr_auto] sm:items-end">
        <div>
            <label for="name" class="label">{{ $editingId ? 'Editar categoria' : 'Nova categoria' }}</label>
            <input wire:model="name" id="name" type="text" class="input" placeholder="Ex.: Pizzas" required>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="description" class="label">Descrição (opcional)</label>
            <input wire:model="description" id="description" type="text" class="input" placeholder="Ex.: Massa de fermentação natural">
            @error('description') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary">{{ $editingId ? 'Salvar' : 'Adicionar' }}</button>
            @if ($editingId)
                <button type="button" wire:click="cancel" class="btn-secondary">Cancelar</button>
            @endif
        </div>
    </form>

    @error('list') <div class="alert-error">{{ $message }}</div> @enderror

    <div class="card overflow-x-auto">
        <table class="table table-cards">
            <thead>
                <tr><th class="w-20" data-card="field">Ordem</th><th data-card="title">Categoria</th><th>Produtos</th><th class="text-right">Ações</th></tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr wire:key="cat-{{ $category->id }}">
                        <td class="whitespace-nowrap">
                            <button type="button" wire:click="move({{ $category->id }}, -1)" class="btn-secondary px-2 py-1" @disabled($loop->first) aria-label="Subir">↑</button>
                            <button type="button" wire:click="move({{ $category->id }}, 1)" class="btn-secondary px-2 py-1" @disabled($loop->last) aria-label="Descer">↓</button>
                        </td>
                        <td>
                            <p class="font-medium text-stone-900">
                                {{ $category->name }}
                                @unless ($category->is_active)
                                    <span class="badge ml-1 bg-stone-200 text-stone-600">inativa</span>
                                @endunless
                            </p>
                            <p class="text-xs text-stone-500">{{ $category->description }}</p>
                        </td>
                        <td>
                            <a href="{{ route('menu.products.index', ['categoria' => $category->id]) }}" class="btn-link" wire:navigate>{{ $category->products_count }}</a>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <button type="button" wire:click="edit({{ $category->id }})" class="btn-link">Editar</button>
                            <button type="button" wire:click="toggle({{ $category->id }})" class="btn-link ml-3">{{ $category->is_active ? 'Desativar' : 'Ativar' }}</button>
                            <button type="button" wire:click="delete({{ $category->id }})" wire:confirm="Excluir a categoria {{ $category->name }}?" class="btn-link ml-3 text-red-700">Excluir</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">
                        <x-ui.empty icon="tag" title="Nenhuma categoria ainda" text="Crie acima (ex.: Pizzas, Bebidas, Sobremesas) ou importe o cardápio pela planilha: as categorias vêm junto.">
                            <a href="{{ route('menu.import') }}" class="btn-secondary" wire:navigate><x-icon name="file" class="size-4" /> Importar planilha</a>
                        </x-ui.empty>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
