<?php

namespace App\Tenancy\Middleware;

use App\Tenancy\Enums\TenantStatus;
use App\Tenancy\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confere o status do tenant identificado e, se estiver ativo, ativa o contexto
 * (search_path, cache, disco e log). Só depois disso a sessão é iniciada.
 */
class EnsureTenantIsActive
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->attributes->get(IdentifyTenant::ATTRIBUTE);

        abort_unless($tenant instanceof Tenant, 404);

        if ($tenant->status === TenantStatus::Suspended || $tenant->status === TenantStatus::Restoring) {
            return response()->view('tenancy.suspended', ['tenant' => $tenant], 403);
        }

        abort_unless($tenant->status === TenantStatus::Active, 404);

        $this->context->activate($tenant);

        return $next($request);
    }
}
