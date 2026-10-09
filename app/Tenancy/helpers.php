<?php

use App\Tenancy\Models\Tenant;
use App\Tenancy\TenantContext;

if (! function_exists('tenant')) {
    function tenant(): ?Tenant
    {
        return app(TenantContext::class)->current();
    }
}
