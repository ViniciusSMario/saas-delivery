<form wire:submit="save" class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('users.index') }}" class="btn-link" wire:navigate>← Usuários</a>
            <h1 class="page-title mt-1">{{ $userId ? 'Editar usuário' : 'Novo usuário' }}</h1>
        </div>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled">Salvar</button>
    </div>

    <section class="card card-body space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="label">Nome</label>
                <input wire:model="name" id="name" type="text" class="input" required>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="email" class="label">E-mail</label>
                <input wire:model="email" id="email" type="email" class="input" required>
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        @unless ($userId)
            <p class="hint">O usuário recebe um e-mail com um link para definir a própria senha.</p>
        @endunless

        @if ($userId && ! $isSelf)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="is_active" class="checkbox"> Usuário ativo
            </label>
            <p class="hint">Desativar bloqueia o acesso imediatamente, sem apagar o histórico.</p>
        @endif
        @error('is_active') <p class="field-error">{{ $message }}</p> @enderror
    </section>

    <section class="card card-body">
        <h2 class="font-semibold">Perfis</h2>
        <p class="hint">As permissões do usuário são a soma dos perfis marcados.</p>
        @error('roleIds') <p class="field-error">{{ $message }}</p> @enderror

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            @foreach ($roles as ['role' => $role, 'assignable' => $assignable])
                <label wire:key="role-{{ $role->id }}" @class([
                    'flex items-start gap-3 rounded-lg border p-3',
                    'border-stone-200' => $assignable,
                    'cursor-not-allowed border-stone-100 bg-stone-50 opacity-60' => ! $assignable,
                ])>
                    <input type="checkbox" value="{{ $role->id }}" wire:model="roleIds" class="checkbox mt-0.5" @disabled(! $assignable)>
                    <span>
                        <span class="block text-sm font-medium">{{ $role->name }}</span>
                        <span class="block text-xs text-stone-500">{{ $role->description }}</span>
                        @unless ($assignable)
                            <span class="block text-xs text-stone-500">Você não pode conceder este perfil.</span>
                        @endunless
                    </span>
                </label>
            @endforeach
        </div>
    </section>
</form>
