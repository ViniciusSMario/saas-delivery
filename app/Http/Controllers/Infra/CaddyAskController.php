<?php

namespace App\Http\Controllers\Infra;

use App\Tenancy\Enums\TenantStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * O Caddy pergunta, antes de emitir um certificado, se o domínio é nosso.
 * 200 = pode emitir; qualquer outra resposta = recusa. Assim ninguém faz o servidor
 * pedir certificado para domínios aleatórios (limite do Let's Encrypt).
 */
class CaddyAskController
{
    public function __invoke(Request $request): Response
    {
        $token = (string) config('platform.caddy_ask_token');

        abort_unless($token !== '' && hash_equals($token, (string) $request->header('X-Caddy-Ask')), 403);

        $domain = mb_strtolower(trim((string) $request->query('domain')));

        return response('', static::allows($domain) ? 200 : 404);
    }

    public static function allows(string $domain): bool
    {
        if ($domain === '' || strlen($domain) > 253) {
            return false;
        }

        if (in_array($domain, [...config('tenancy.central_domains'), config('tenancy.platform_domain')], true)) {
            return true;
        }

        // Subdomínio de estabelecimento, ou domínio próprio já verificado, de tenant que responde.
        return DB::connection('central')->table('tenant_domains')
            ->join('tenants', 'tenants.id', '=', 'tenant_domains.tenant_id')
            ->where('tenant_domains.domain', $domain)
            ->where(fn ($q) => $q->where('tenant_domains.type', 'subdomain')->orWhereNotNull('tenant_domains.verified_at'))
            ->whereIn('tenants.status', [TenantStatus::Active->value, TenantStatus::Suspended->value, TenantStatus::PendingDeletion->value, TenantStatus::Restoring->value])
            ->whereNull('tenants.deleted_at')
            ->exists();
    }
}
