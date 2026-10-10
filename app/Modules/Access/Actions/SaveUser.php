<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria ou altera um usuário do estabelecimento. Um usuário novo recebe um e-mail
 * para definir a senha; ninguém digita a senha de outra pessoa.
 */
class SaveUser
{
    public function __construct(private readonly SendSetPasswordLink $sendLink)
    {
    }

    /**
     * @param  array{name: string, email: string, is_active?: bool}  $data
     * @param  list<int>  $roleIds
     */
    public function handle(?User $user, array $data, array $roleIds, ?User $actor = null): User
    {
        $isNew = $user === null;
        $user ??= new User;
        $roles = Role::query()->whereKey($roleIds)->get();

        $this->authorize($user, $roles, $data, $actor);

        // Limite do plano conta usuários ativos (desativar libera a vaga).
        if (($data['is_active'] ?? true) && ($isNew || ! $user->is_active)) {
            tenant()?->assertWithinLimit('users', User::query()->where('is_active', true)->count(), 'email');
        }

        DB::connection('tenant')->transaction(function () use ($user, $data, $roles, $isNew, $actor) {
            $previousRoles = $isNew ? [] : $user->roles()->pluck('name')->sort()->values()->all();

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'is_active' => $data['is_active'] ?? true,
            ])->save();

            $user->roles()->sync($roles->modelKeys());
            $user->flushPermissionCache();

            $changes = $isNew ? ['nome' => $user->name, 'email' => $user->email] : Audit::diff($user);
            $newRoles = $roles->pluck('name')->sort()->values()->all();

            if ($previousRoles !== $newRoles) {
                $changes['perfis'] = ['de' => $previousRoles, 'para' => $newRoles];
            }

            Audit::record($isNew ? 'user.created' : 'user.updated', $user, $changes, $actor);
        });

        if ($isNew) {
            $this->sendLink->handle($user, invitation: true);
            Audit::record('user.invited', $user, ['email' => $user->email], $actor);
        }

        return $user;
    }

    private function authorize(User $user, $roles, array $data, ?User $actor): void
    {
        if ($actor === null) {
            return;
        }

        $actor->loadMissing('roles');

        if ($user->exists && $user->isOwner() && ! $actor->isOwner()) {
            throw ValidationException::withMessages(['email' => 'Só um dono pode alterar outro dono.']);
        }

        // Só os perfis que mudam (concedidos ou retirados) precisam estar ao alcance de quem edita.
        $current = $user->exists ? $user->roles()->get() : new EloquentCollection;
        $changed = $roles->whereNotIn('id', $current->modelKeys())
            ->merge($current->whereNotIn('id', $roles->modelKeys()));

        foreach ($changed as $role) {
            if (! $actor->canAssignRole($role)) {
                throw ValidationException::withMessages(['roleIds' => "Você não pode conceder o perfil {$role->name}."]);
            }
        }

        if ($user->exists && $user->is($actor)) {
            if (($data['is_active'] ?? true) === false) {
                throw ValidationException::withMessages(['is_active' => 'Você não pode desativar o próprio usuário.']);
            }

            if ($actor->isOwner() && ! $roles->contains('is_owner', true)) {
                throw ValidationException::withMessages(['roleIds' => 'Você não pode remover o próprio perfil de dono.']);
            }
        }

        // Sempre resta pelo menos um dono ativo.
        if ($user->exists && $user->isOwner()) {
            $staysOwner = $roles->contains('is_owner', true) && ($data['is_active'] ?? true);

            if (! $staysOwner && $this->activeOwnersExcept($user) === 0) {
                throw ValidationException::withMessages(['roleIds' => 'O estabelecimento precisa de pelo menos um dono ativo.']);
            }
        }
    }

    private function activeOwnersExcept(User $user): int
    {
        return User::query()
            ->whereKeyNot($user->getKey())
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('is_owner', true))
            ->count();
    }
}
