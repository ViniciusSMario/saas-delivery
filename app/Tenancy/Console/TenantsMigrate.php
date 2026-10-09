<?php

namespace App\Tenancy\Console;

use App\Tenancy\TenantMigrator;

class TenantsMigrate extends TenantCommand
{
    protected $signature = 'tenants:migrate {--tenant= : slug de um único tenant} {--pretend : mostra o SQL sem executar}';

    protected $description = 'Roda as migrations de tenant em cada schema (ativos e suspensos, ou um só)';

    public function handle(TenantMigrator $migrator): int
    {
        return $this->eachTenant('migrated', fn ($tenant) => $migrator->migrate($tenant, (bool) $this->option('pretend')));
    }
}
