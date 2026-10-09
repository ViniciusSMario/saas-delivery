<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\User;
use App\Modules\Access\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveRole
{
    /**
     * @param  array{name: string, description?: string|null}  $data
     * @param  list<string>  $permissions
     */
    public function handle(?Role $role, array $data, array $permissions, ?User $actor = null): Role
    {
        $isNew = $role === null;
        $role ??= new Role;

        if ($role->is_owner) {
            throw ValidationException::withMessages(['name' => 'O perfil de dono tem todas as permissões e não pode ser alterado.']);
        }

        $permissions = array_values(array_intersect($permissions, Permission::values()));

        if ($actor && ! $actor->isOwner()) {
            $missing = array_diff($permissions, array_keys($actor->permissions()));

            if ($missing !== []) {
                throw ValidationException::withMessages(['permissions' => 'Você não pode conceder permissões que não tem.']);
            }
        }

        DB::connection('tenant')->transaction(function () use ($role, $data, $permissions, $isNew, $actor) {
            $before = $isNew ? [] : array_map(fn (Permission $p) => $p->value, $role->permissions());

            $role->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            if ($isNew) {
                $role->slug = $this->uniqueSlug($data['name']);
            }

            $role->save();
            $role->syncPermissions($permissions);

            $changes = $isNew ? ['nome' => $role->name] : Audit::diff($role);
            sort($before);
            $after = $permissions;
            sort($after);

            if ($before !== $after) {
                $changes['permissoes'] = ['de' => $before, 'para' => $after];
            }

            Audit::record($isNew ? 'role.created' : 'role.updated', $role, $changes, $actor);
        });

        return $role;
    }

    public function delete(Role $role, ?User $actor = null): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages(['role' => 'Perfis do sistema não podem ser excluídos.']);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => 'Remova o perfil dos usuários antes de excluí-lo.']);
        }

        Audit::record('role.deleted', $role, ['nome' => $role->name], $actor);
        $role->delete();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'perfil';
        $slug = $base;

        for ($i = 2; Role::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
