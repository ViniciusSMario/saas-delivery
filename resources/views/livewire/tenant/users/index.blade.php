<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">Usuários</h1>
        @can('users.manage')
            <a href="{{ route('users.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> Novo usuário</a>
        @endcan
    </div>

    <div class="flex flex-wrap gap-3">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar por nome ou e-mail" class="input max-w-xs" aria-label="Buscar">
        <select wire:model.live="status" class="input w-auto" aria-label="Situação">
            <option value="active">Ativos</option>
            <option value="inactive">Desativados</option>
            <option value="all">Todos</option>
        </select>
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Perfis</th>
                    <th>Último acesso</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td>
                            <p class="font-medium text-stone-900">{{ $user->name }}</p>
                            <p class="text-xs text-stone-500">{{ $user->email }}</p>
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($user->roles as $role)
                                    <span @class(['badge', 'bg-brand-100 text-brand-700' => $role->is_owner, 'bg-stone-100 text-stone-700' => ! $role->is_owner])>{{ $role->name }}</span>
                                @endforeach
                                @unless ($user->is_active)
                                    <span class="badge bg-red-100 text-red-700">Desativado</span>
                                @endunless
                                @if (! $user->password)
                                    <span class="badge bg-amber-100 text-amber-800">Convite pendente</span>
                                @endif
                            </div>
                        </td>
                        <td class="text-stone-500">{{ $user->last_login_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-right whitespace-nowrap">
                            @can('users.manage')
                                @if (! $user->password && $user->is_active)
                                    <button type="button" wire:click="resendInvite({{ $user->id }})" class="btn-link mr-3">Reenviar convite</button>
                                @endif
                                <a href="{{ route('users.edit', $user) }}" class="btn-link" wire:navigate>Editar</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-ui.empty icon="user" title="Nenhum usuário encontrado" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
</div>
