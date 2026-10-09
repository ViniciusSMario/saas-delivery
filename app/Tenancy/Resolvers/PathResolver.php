<?php

namespace App\Tenancy\Resolvers;

use App\Tenancy\Models\Tenant;
use Illuminate\Http\Request;

/** Rotas como meusistema.com/cardapio/{tenant_slug}. */
class PathResolver implements TenantResolver
{
    public const PARAMETER = 'tenant_slug';

    public function resolve(Request $request): ?Tenant
    {
        $route = $request->route();
        $slug = $route?->parameter(self::PARAMETER);

        if (! is_string($slug)) {
            return null;
        }

        // Os controllers não recebem o slug: o tenant fica disponível via tenant().
        $route->forgetParameter(self::PARAMETER);

        return Tenant::query()->where('slug', mb_strtolower($slug))->first();
    }
}
