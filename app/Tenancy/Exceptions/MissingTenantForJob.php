<?php

namespace App\Tenancy\Exceptions;

class MissingTenantForJob extends TenancyException
{
    public static function onDispatch(string $job): self
    {
        return new self("O job [{$job}] usa TenantAware e foi despachado sem tenant ativo.");
    }

    public static function onHandle(string $job): self
    {
        return new self("O job [{$job}] chegou ao worker sem um tenant válido no payload.");
    }
}
