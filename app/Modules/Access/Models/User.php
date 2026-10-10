<?php

namespace App\Modules\Access\Models;

use App\Modules\Access\Notifications\SetPasswordNotification;
use App\Modules\Access\Permission;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

/**
 * Usuário do estabelecimento. Vive no schema do tenant: o mesmo e-mail pode
 * existir em dois estabelecimentos como contas independentes.
 */
class User extends Authenticatable
{
    use Notifiable;

    protected $connection = 'tenant';

    protected $fillable = ['name', 'email', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['is_active' => true];

    /** @var array<string, true>|null permissões carregadas uma vez por requisição */
    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (User $user) => $user->email = mb_strtolower(trim($user->email)));
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function isOwner(): bool
    {
        return $this->roles->contains('is_owner', true);
    }

    public function hasPermission(Permission $permission): bool
    {
        return isset($this->permissions()[$permission->value]);
    }

    /** @return array<string, true> */
    public function permissions(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }

        if ($this->isOwner()) {
            return $this->permissionCache = array_fill_keys(Permission::values(), true);
        }

        $values = DB::connection($this->getConnectionName())
            ->table('role_permissions')
            ->whereIn('role_id', $this->roles->modelKeys())
            ->distinct()
            ->pluck('permission')
            ->all();

        return $this->permissionCache = array_fill_keys($values, true);
    }

    /**
     * "Modo app": quem só atende mesas e/ou faz entregas (garçom, entregador) usa
     * o painel como um app de celular, com abas embaixo e sem a barra lateral.
     */
    public function usesFieldMode(): bool
    {
        $field = [Permission::OrdersView, Permission::OrdersManage, Permission::OrdersCancel, Permission::OrdersDiscount, Permission::TablesManage, Permission::DeliveriesOperate];

        return ($this->hasPermission(Permission::TablesManage) || $this->hasPermission(Permission::DeliveriesOperate))
            && array_diff(array_keys($this->permissions()), array_map(fn (Permission $p) => $p->value, $field)) === [];
    }

    /** Tela inicial do modo app. */
    public function fieldHome(): string
    {
        return $this->hasPermission(Permission::TablesManage) ? 'tables.map' : 'delivery.board';
    }

    /**
     * Evita escalada de privilégio: só o dono concede o perfil de dono, e ninguém
     * concede um perfil com permissões que ele mesmo não tem.
     */
    public function canAssignRole(Role $role): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        if ($role->is_owner) {
            return false;
        }

        foreach ($role->permissions() as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roles');
    }

    /** Chamado pelo broker de senha ("esqueci minha senha"). */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new SetPasswordNotification($token));
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
