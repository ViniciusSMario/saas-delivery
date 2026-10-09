<?php

namespace App\Tenancy\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sessão presa ao tenant: uma sessão de outro tenant é derrubada. */
class EnsureSessionBelongsToTenant
{
    public const KEY = 'tenant_id';

    public function __construct(private readonly TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        $tenantId = $this->context->id();

        if ($session->has(self::KEY) && $session->get(self::KEY) !== $tenantId) {
            $session->invalidate();
            $session->regenerateToken();
        }

        $session->put(self::KEY, $tenantId);

        return $next($request);
    }
}
