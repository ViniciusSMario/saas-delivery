<?php

namespace App\Modules\Access\Models;

use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends TenantModel
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'auth.login' => 'Entrou no sistema',
            'auth.logout' => 'Saiu do sistema',
            'auth.password_reset' => 'Definiu uma nova senha',
            'user.created' => 'Criou usuário',
            'user.updated' => 'Alterou usuário',
            'user.invited' => 'Enviou convite',
            'role.created' => 'Criou perfil',
            'role.updated' => 'Alterou perfil',
            'role.deleted' => 'Excluiu perfil',
            'establishment.updated' => 'Alterou dados do estabelecimento',
            'settings.updated' => 'Alterou configurações',
            'opening_hours.updated' => 'Alterou horário de funcionamento',
            default => $this->action,
        };
    }
}
