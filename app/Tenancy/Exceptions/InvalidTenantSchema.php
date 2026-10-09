<?php

namespace App\Tenancy\Exceptions;

class InvalidTenantSchema extends TenancyException
{
    public function __construct(string $schema)
    {
        parent::__construct("Nome de schema de tenant inválido: [{$schema}].");
    }
}
