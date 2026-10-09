<?php

namespace App\Tenancy\Exceptions;

class TenantSchemaMissing extends TenancyException
{
    public function __construct(string $schema)
    {
        parent::__construct("O schema [{$schema}] não existe ou não está acessível.");
    }
}
