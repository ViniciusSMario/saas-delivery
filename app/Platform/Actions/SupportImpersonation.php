<?php

namespace App\Platform\Actions;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use App\Platform\Models\PlatformUser;
use App\Platform\Models\SupportAccess;
use App\Tenancy\Models\Tenant;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantUrl;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Entrar como suporte": a equipe gera um link de uso único (2 minutos) para o
 * endereço do estabelecimento, que faz login como um usuário dele. Tudo fica
 * registrado na plataforma (support_accesses, tenant_events) e na auditoria do
 * estabelecimento, que também mostra o aviso de modo suporte na tela.
 */
class SupportImpersonation
{
    public const SESSION_KEY = 'support_access';

    public const TTL_SECONDS = 120;

    public function __construct(private readonly TenantContext $context)
    {
    }

    /** @return string link para abrir no navegador */
    public function start(Tenant $tenant, PlatformUser $staff, int $tenantUserId, string $reason, ?string $ip = null): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['supportReason' => 'Descreva o motivo do acesso (ex.: chamado #123, cliente pediu ajuda no cardápio).']);
        }

        $user = $this->context->run($tenant, fn () => User::where('is_active', true)->find($tenantUserId))
            ?? throw ValidationException::withMessages(['supportUserId' => 'Usuário não encontrado ou desativado.']);

        $token = Str::random(48);

        SupportAccess::create([
            'tenant_id' => $tenant->id,
            'platform_user_id' => $staff->id,
            'platform_email' => $staff->email,
            'tenant_user_id' => $user->id,
            'tenant_user_name' => $user->name,
            'reason' => mb_substr($reason, 0, 300),
            'token_hash' => hash('sha256', $token),
            'ip' => $ip,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS),
        ]);

        $tenant->recordEvent('support_access', ['usuario' => $user->name, 'motivo' => $reason], $staff->email);

        return TenantUrl::to($tenant, 'suporte/entrar/'.$token);
    }

    /** Troca o link pelo acesso: confere tenant, validade e uso único. */
    public function redeem(Tenant $tenant, string $token): ?SupportAccess
    {
        $access = SupportAccess::where('token_hash', hash('sha256', $token))->first();

        if (! $access || $access->tenant_id !== $tenant->id || $access->used_at || $access->expires_at->isPast()) {
            return null;
        }

        // Uso único mesmo com dois cliques simultâneos.
        $claimed = SupportAccess::whereKey($access->id)->whereNull('used_at')->update(['used_at' => now()]);

        return $claimed ? $access->refresh() : null;
    }

    public function audit(SupportAccess $access, User $user): void
    {
        Audit::record('support.login', $user, ['equipe' => $access->platform_email, 'motivo' => $access->reason], $user);
    }

    public function end(int $accessId): void
    {
        SupportAccess::whereKey($accessId)->whereNull('ended_at')->update(['ended_at' => now()]);
    }
}
