<?php

namespace App\Modules\Push\Models;

use App\Modules\Access\Models\User;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Celular (navegador) inscrito para receber notificações, ligado ao usuário que está logado nele. */
class PushSubscription extends TenantModel
{
    protected $fillable = ['user_id', 'endpoint', 'public_key', 'auth_token', 'content_encoding', 'user_agent', 'last_used_at'];

    protected $hidden = ['public_key', 'auth_token'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
