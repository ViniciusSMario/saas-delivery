<?php

namespace App\Tenancy\Jobs;

use App\Tenancy\Exceptions\MissingTenantForJob;
use App\Tenancy\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;

class RunInTenant
{
    public const PAYLOAD_KEY = 'tenant_id';

    public function handle(object $job, Closure $next): mixed
    {
        $tenantId = $job->job?->payload()[self::PAYLOAD_KEY] ?? null;
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        if ($tenant === null) {
            throw MissingTenantForJob::onHandle($job::class);
        }

        return app(TenantContext::class)->run($tenant, fn () => $next($job));
    }
}
