<?php

namespace App\Modules\Access\Models;

use App\Modules\Access\Permission;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Role extends TenantModel
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_owner' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** @return list<Permission> */
    public function permissions(): array
    {
        if ($this->is_owner) {
            return Permission::cases();
        }

        return DB::connection($this->getConnectionName())
            ->table('role_permissions')
            ->where('role_id', $this->getKey())
            ->pluck('permission')
            ->map(fn (string $value) => Permission::tryFrom($value))
            ->filter()
            ->values()
            ->all();
    }

    /** @param  list<Permission|string>  $permissions */
    public function syncPermissions(array $permissions): void
    {
        $values = collect($permissions)
            ->map(fn ($p) => $p instanceof Permission ? $p->value : Permission::from($p)->value)
            ->unique()
            ->values();

        $db = DB::connection($this->getConnectionName());

        $db->transaction(function () use ($db, $values) {
            $db->table('role_permissions')->where('role_id', $this->getKey())->delete();
            $db->table('role_permissions')->insert(
                $values->map(fn (string $value) => ['role_id' => $this->getKey(), 'permission' => $value])->all()
            );
        });
    }
}
