<?php

namespace App\Tenancy\Resolvers;

use App\Tenancy\Models\Tenant;
use Illuminate\Http\Request;

/** Subdomínio (empresa1.meusistema.com) ou domínio próprio, via public.tenant_domains. */
class DomainResolver implements TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        $host = mb_strtolower($request->getHost());

        if (in_array($host, [...config('tenancy.central_domains'), config('tenancy.platform_domain')], true)) {
            return null;
        }

        // Domínio próprio só depois de verificado (prova de posse por TXT).
        return Tenant::query()
            ->whereHas('domains', fn ($query) => $query->where('domain', $host)
                ->where(fn ($q) => $q->where('type', 'subdomain')->orWhereNotNull('verified_at')))
            ->first();
    }
}
