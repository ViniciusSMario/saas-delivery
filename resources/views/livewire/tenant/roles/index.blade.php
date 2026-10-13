<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="page-title">Perfis e permissões</h1>
            <p class="mt-1 text-sm text-stone-500">Cada perfil agrupa permissões. Um usuário pode ter mais de um perfil.</p>
        </div>
        <a href="{{ route('roles.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> Novo perfil</a>
    </div>


    <div class="card overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Perfil</th>
                    <th>Usuários</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr wire:key="role-{{ $role->id }}">
                        <td>
                            <p class="font-medium text-stone-900">
                                {{ $role->name }}
                                @if ($role->is_owner)
                                    <span class="badge ml-1 bg-brand-100 text-brand-700">acesso total</span>
                                @elseif ($role->is_system)
                                    <span class="badge ml-1 bg-stone-100 text-stone-600">padrão</span>
                                @endif
                            </p>
                            <p class="text-xs text-stone-500">{{ $role->description }}</p>
                        </td>
                        <td>{{ $role->users_count }}</td>
                        <td class="text-right whitespace-nowrap">
                            @unless ($role->is_owner)
                                <a href="{{ route('roles.edit', $role) }}" class="btn-link" wire:navigate>Editar</a>
                            @endunless
                            @unless ($role->is_system)
                                <button type="button" wire:click="delete({{ $role->id }})" wire:confirm="Excluir o perfil {{ $role->name }}?" class="btn-link ml-3 text-red-700">Excluir</button>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
