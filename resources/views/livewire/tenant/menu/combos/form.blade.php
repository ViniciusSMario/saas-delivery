<form wire:submit="save" class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('menu.combos.index') }}" class="btn-link" wire:navigate>← Combos</a>
            <h1 class="page-title mt-1">{{ $comboId ? 'Editar combo' : 'Novo combo' }}</h1>
        </div>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled">Salvar</button>
    </div>

    <section class="card card-body grid gap-4 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <label for="name" class="label">Nome</label>
            <input wire:model="name" id="name" type="text" class="input" placeholder="Ex.: Combo Família" required>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="price" class="label">Preço do combo (R$)</label>
            <input wire:model.blur="price" id="price" type="text" inputmode="decimal" class="input" placeholder="59,90" required>
            @error('price') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-3">
            <label for="description" class="label">Descrição</label>
            <textarea wire:model="description" id="description" rows="2" class="input"></textarea>
        </div>
        <label class="flex items-center gap-2 text-sm sm:col-span-3"><input type="checkbox" wire:model="is_active" class="checkbox"> Ativo</label>
    </section>

    <section class="card card-body">
        <h2 class="font-semibold">Itens</h2>
        @error('items') <p class="field-error">{{ $message }}</p> @enderror

        <div class="mt-4 space-y-2">
            @foreach ($items as $index => $item)
                <div wire:key="item-{{ $index }}" class="grid grid-cols-[5rem_1fr_auto] items-start gap-2">
                    <div>
                        <input wire:model.live.debounce.400ms="items.{{ $index }}.quantity" type="number" min="1" max="20" class="input" aria-label="Quantidade">
                        @error("items.$index.quantity") <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <select wire:model.live="items.{{ $index }}.product_id" class="input" aria-label="Produto">
                            <option value="">Escolha um produto</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} · {{ $product->category->name }} · {{ $product->price_cents->format() }}</option>
                            @endforeach
                        </select>
                        @error("items.$index.product_id") <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" wire:click="removeItem({{ $index }})" class="btn-secondary h-9 px-3 text-red-700" aria-label="Remover item" @disabled(count($items) === 1)>×</button>
                </div>
            @endforeach
        </div>

        <button type="button" wire:click="addItem" class="btn-secondary mt-3">+ Adicionar item</button>

        <p class="mt-4 text-sm text-stone-600">
            Avulso: <strong>{{ $regular->format() }}</strong>
            @if ($priceValue && $regular->greaterThan($priceValue))
                · o cliente economiza <strong class="text-green-700">{{ $regular->subtract($priceValue)->format() }}</strong>
            @elseif ($priceValue && ! $regular->isZero())
                · <span class="text-amber-700">o combo não está mais barato que os itens avulsos</span>
            @endif
        </p>
    </section>

    <section class="card card-body">
        <h2 class="font-semibold">Foto</h2>
        <div class="mt-3 flex flex-wrap items-center gap-4">
            @if ($image && $image->isPreviewable() && ! $errors->has('image'))
                <img src="{{ $image->temporaryUrl() }}" alt="" class="size-24 rounded-lg object-cover">
            @elseif ($hasImage)
                <img src="{{ route('menu.combos.image', $comboId) }}" alt="" class="size-24 rounded-lg object-cover">
            @endif
            <input wire:model="image" type="file" accept="image/jpeg,image/png,image/webp" class="text-sm">
        </div>
        @error('image') <p class="field-error">{{ $message }}</p> @enderror
    </section>
</form>
