<?php

namespace App\Tenancy\Middleware;

use App\Tenancy\Models\Tenant;
use App\Tenancy\Resolvers\TenantResolver;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Primeiro middleware do grupo "tenant": só localiza o registro em public.tenants.
 * Nenhuma query no schema de um estabelecimento roda aqui.
 */
class IdentifyTenant
{
    public const ATTRIBUTE = 'tenancy.tenant';

    public function __construct(private readonly TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolve($request);

        abort_if($tenant === null, 404);

        $request->attributes->set(self::ATTRIBUTE, $tenant);

        return $next($request);
    }

    /** Fim da requisição: conexão volta ao estado fechado. */
    public function terminate(Request $request, Response $response): void
    {
        $this->context->reset();
    }

    private function resolve(Request $request): ?Tenant
    {
        foreach (config('tenancy.resolvers') as $class) {
            /** @var TenantResolver $resolver */
            $resolver = app($class);

            if ($tenant = $resolver->resolve($request)) {
                return $tenant;
            }
        }

        return null;
    }
}
