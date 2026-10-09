<?php

namespace App\Tenancy\Jobs;

/**
 * Para jobs que rodam no schema de um tenant.
 *
 * No despacho, o id do tenant ativo vai para o payload (TenancyServiceProvider);
 * despachar sem tenant ativo é erro. No worker, o middleware abaixo reativa
 * esse tenant e volta ao estado fechado no fim.
 */
trait TenantAware
{
    public function middleware(): array
    {
        return [new RunInTenant];
    }
}
