<form wire:submit="save" class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('menu.addon-groups.index') }}" class="btn-link" wire:navigate>← Adicionais</a>
            <h1 class="page-title mt-1">{{ $groupId ? 'Editar grupo' : 'Novo grupo de adicionais' }}</h1>
        </div>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled">Salvar</button>
    </div>

    <section class="card card-body grid gap-4 sm:grid-cols-4">
        <div class="sm:col-span-2">
            <label for="name" class="label">Nome do grupo</label>
            <input wire:model="name" id="name" type="text" class="input" placeholder="Ex.: Escolha a borda" required>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="min_choices" class="label">Mínimo</label>
            <input wire:model="min_choices" id="min_choices" type="number" min="0" class="input">
            <p class="hint">0 = opcional</p>
            @error('min_choices') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="max_choices" class="label">Máximo</label>
            <input wire:model="max_choices" id="max_choices" type="number" min="1" class="input">
            @error('max_choices') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <label class="flex items-center gap-2 text-sm sm:col-span-4"><input type="checkbox" wire:model="is_active" class="checkbox"> Grupo ativo</label>
    </section>

    <section class="card card-body">
        <h2 class="font-semibold">Opções</h2>
        @error('addons') <p class="field-error">{{ $message }}</p> @enderror

        <div class="mt-4 space-y-2">
            @foreach ($addons as $index => $addon)
                <div wire:key="addon-{{ $index }}-{{ $addon['id'] ?? 'new' }}" class="grid grid-cols-[1fr_8rem_auto_auto] items-start gap-2">
                    <div>
                        <input wire:model="addons.{{ $index }}.name" type="text" class="input" placeholder="Ex.: Catupiry" aria-label="Nome da opção">
                        @error("addons.$index.name") <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <input wire:model="addons.{{ $index }}.price" type="text" inputmode="decimal" class="input" aria-label="Preço adicional">
                        @error("addons.$index.price") <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex h-9 items-center gap-1 text-xs text-stone-600"><input type="checkbox" wire:model="addons.{{ $index }}.is_active" class="checkbox"> ativa</label>
                    <button type="button" wire:click="removeOption({{ $index }})" class="btn-secondary h-9 px-3 text-red-700" aria-label="Remover opção" @disabled(count($addons) === 1)>×</button>
                </div>
            @endforeach
        </div>

        <button type="button" wire:click="addOption" class="btn-secondary mt-3">+ Adicionar opção</button>
        <p class="hint mt-2">Preço 0,00 = sem custo adicional.</p>
    </section>
</form>
