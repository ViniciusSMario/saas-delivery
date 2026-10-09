<?php

namespace App\Tenancy\Exceptions;

use App\Tenancy\Models\Tenant;

class TenantAlreadyActive extends TenancyException
{
    public function __construct(Tenant $current, Tenant $requested)
    {
        parent::__construct("Já existe um tenant ativo [{$current->slug}]; use TenantContext::run() para executar algo em [{$requested->slug}].");
    }
}
