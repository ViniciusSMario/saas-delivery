<form wire:submit="save" class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('menu.products.index') }}" class="btn-link" wire:navigate>← Produtos</a>
            <h1 class="page-title mt-1">{{ $productId ? 'Editar produto' : 'Novo produto' }}</h1>
        </div>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled">Salvar</button>
    </div>

    <section class="card card-body grid gap-4 sm:grid-cols-6">
        <div class="sm:col-span-4">
            <label for="name" class="label">Nome</label>
            <input wire:model="name" id="name" type="text" class="input" placeholder="Ex.: Pizza Calabresa" required>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label for="code" class="label">Código (PDV)</label>
            <input wire:model="code" id="code" type="text" class="input font-mono" placeholder="Ex.: 101">
            @error('code') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-3">
            <label for="category_id" class="label">Categoria</label>
            <select wire:model="category_id" id="category_id" class="input">
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            @error('category_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-3">
            <label for="price" class="label">Preço (R$)</label>
            <input wire:model="price" id="price" type="text" inputmode="decimal" class="input" placeholder="39,90" required>
            @error('price') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-6">
            <label for="description" class="label">Descrição</label>
            <textarea wire:model="description" id="description" rows="3" class="input" placeholder="Ingredientes, tamanho, serve quantas pessoas..."></textarea>
            @error('description') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="flex flex-wrap gap-6 sm:col-span-6">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="checkbox"> Ativo (aparece no cardápio)</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_available" class="checkbox"> Disponível (desmarque se esgotar)</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_featured" class="checkbox"> Destaque</label>
        </div>
    </section>

    <section class="card card-body">
        <h2 class="font-semibold">Fotos</h2>
        <p class="hint">Até {{ $maxImages }} fotos (JPG, PNG ou WEBP, até 4 MB cada). A primeira é a capa.</p>

        <div class="mt-4 flex flex-wrap gap-3">
            @foreach ($images as $image)
                <div wire:key="img-{{ $image->id }}" class="relative w-28">
                    <img src="{{ route('menu.images.show', $image) }}" alt="" class="aspect-square w-full rounded-lg border border-stone-200 object-cover">
                    <div class="mt-1 flex justify-between text-xs">
                        @if ($loop->first)
                            <span class="font-medium text-brand-700">Capa</span>
                        @else
                            <button type="button" wire:click="makeCover({{ $image->id }})" class="btn-link text-xs">Usar como capa</button>
                        @endif
                        <button type="button" wire:click="removeImage({{ $image->id }})" wire:confirm="Remover esta foto?" class="text-red-700 hover:underline">Remover</button>
                    </div>
                </div>
            @endforeach

            @foreach ($newImages as $index => $file)
                <div wire:key="new-{{ $index }}" class="w-28">
                    @if ($file->isPreviewable() && ! $errors->has('newImages.'.$index))
                        <img src="{{ $file->temporaryUrl() }}" alt="" class="aspect-square w-full rounded-lg border-2 border-dashed border-brand-500 object-cover">
                    @else
                        <div class="flex aspect-square w-full items-center justify-center rounded-lg border-2 border-dashed border-red-300 text-xs text-red-700">inválida</div>
                    @endif
                    <button type="button" wire:click="removeNewImage({{ $index }})" class="mt-1 text-xs text-red-700 hover:underline">Remover</button>
                </div>
            @endforeach
        </div>

        @if ($images->count() + count($newImages) < $maxImages)
            <input wire:model="newImages" type="file" multiple accept="image/jpeg,image/png,image/webp" class="mt-4 text-sm">
        @endif
        <div wire:loading wire:target="newImages" class="hint">Enviando…</div>
        @error('newImages') <p class="field-error">{{ $message }}</p> @enderror
        @error('newImages.*') <p class="field-error">{{ $message }}</p> @enderror
    </section>

    <section class="card card-body">
        <h2 class="font-semibold">Adicionais e opções</h2>
        <p class="hint">Grupos como "Escolha a borda" ou "Adicionais". Crie e edite os grupos em <a href="{{ route('menu.addon-groups.index') }}" class="btn-link text-xs" wire:navigate>Adicionais</a>.</p>

        @if ($selectedGroups->isNotEmpty())
            <ul class="mt-4 divide-y divide-stone-100 rounded-lg border border-stone-200">
                @foreach ($selectedGroups as $group)
                    <li wire:key="sel-{{ $group->id }}" class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                        <span>
                            <span class="font-medium">{{ $group->name }}</span>
                            <span class="text-xs text-stone-500">· {{ $group->rule() }} · {{ $group->addons_count }} opções{{ $group->is_active ? '' : ' · inativo' }}</span>
                        </span>
                        <span class="flex gap-1 whitespace-nowrap">
                            <button type="button" wire:click="moveAddonGroup({{ $group->id }}, -1)" class="btn-secondary px-2 py-1" @disabled($loop->first) aria-label="Subir">↑</button>
                            <button type="button" wire:click="moveAddonGroup({{ $group->id }}, 1)" class="btn-secondary px-2 py-1" @disabled($loop->last) aria-label="Descer">↓</button>
                            <button type="button" wire:click="$set('addonGroupIds', {{ json_encode(array_values(array_diff($addonGroupIds, [$group->id]))) }})" class="btn-secondary px-2 py-1 text-red-700">Tirar</button>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($availableGroups->isNotEmpty())
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($availableGroups as $group)
                    <button type="button" wire:key="av-{{ $group->id }}" wire:click="$set('addonGroupIds', {{ json_encode([...$addonGroupIds, $group->id]) }})"
                            class="rounded-full border border-dashed border-stone-300 px-3 py-1 text-sm hover:border-brand-500 hover:text-brand-700">+ {{ $group->name }}</button>
                @endforeach
            </div>
        @elseif ($selectedGroups->isEmpty())
            <p class="mt-4 text-sm text-stone-500">Nenhum grupo de adicionais criado ainda.</p>
        @endif
        @error('addonGroupIds') <p class="field-error">{{ $message }}</p> @enderror
    </section>

    <div class="flex justify-end">
        <button type="submit" class="btn-primary" wire:loading.attr="disabled">Salvar</button>
    </div>
</form>
