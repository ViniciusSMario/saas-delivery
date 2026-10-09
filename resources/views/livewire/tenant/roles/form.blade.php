<form wire:submit="save" class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('roles.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-stone-500 hover:text-stone-800" wire:navigate><x-icon name="chevron-left" class="size-4" /> Perfis</a>
            <h1 class="page-title mt-1">{{ $roleId ? 'Editar perfil' : 'Novo perfil' }}</h1>
        </div>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled"><x-icon name="check" class="size-4" /> Salvar</button>
    </div>

    <section class="card card-body grid gap-4 sm:grid-cols-2">
        <div>
            <label for="name" class="label">Nome</label>
            <input wire:model="name" id="name" type="text" class="input" required>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="description" class="label">Descrição</label>
            <input wire:model="description" id="description" type="text" class="input">
            @error('description') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </section>

    <section class="card card-body">
        <h2 class="section-title">Permissões</h2>
        @error('permissions') <p class="field-error">{{ $message }}</p> @enderror

        <div class="mt-4 grid gap-6 sm:grid-cols-2">
            @foreach ($groups as $group => $permissions)
                <fieldset>
                    <legend class="text-xs font-semibold tracking-wide text-stone-500 uppercase">{{ $group }}</legend>
                    <div class="mt-2 space-y-2">
                        @foreach ($permissions as $permission)
                            <label class="flex items-center gap-2 text-sm" wire:key="perm-{{ $permission->value }}">
                                <input type="checkbox" value="{{ $permission->value }}" wire:model="permissions" class="checkbox">
                                {{ $permission->label() }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>
        <p class="hint mt-4">Permissões de cardápio, pedidos, caixa e estoque passam a valer quando esses módulos forem liberados.</p>
    </section>
</form>
