<?php

use App\Tenancy\Actions\ProvisionTenant;
use App\Tenancy\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;

pest()->extend(Tests\TestCase::class)->in('Feature', 'Unit', 'Tenancy', 'Arch');

/** Um dos tenants de referência criados no início da suíte (teste1, teste2). */
function tenantBySlug(string $slug): Tenant
{
    return Tenant::where('slug', $slug)->firstOrFail();
}

/** Provisiona um tenant novo, com slug único, para testes que alteram o tenant. */
function provisionTenant(string $prefix = 't'): Tenant
{
    return app(ProvisionTenant::class)->handle($prefix.Str::lower(Str::random(8)), 'Tenant '.$prefix);
}

/**
 * @template T
 *
 * @param  Closure(Tenant): T  $callback
 * @return T
 */
function inTenant(Tenant $tenant, Closure $callback): mixed
{
    return app(TenantContext::class)->run($tenant, $callback);
}

function tenantUrl(Tenant $tenant, string $path = '/'): string
{
    return 'http://'.$tenant->primaryDomain().$path;
}
