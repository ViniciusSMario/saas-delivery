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

        if (in_array($host, config('tenancy.central_domains'), true)) {
            return null;
        }

        return Tenant::query()
            ->whereHas('domains', fn ($query) => $query->where('domain', $host))
            ->first();
    }
}
