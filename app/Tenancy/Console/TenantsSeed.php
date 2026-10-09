<?php

namespace App\Tenancy\Console;

use App\Tenancy\TenantMigrator;

class TenantsSeed extends TenantCommand
{
    protected $signature = 'tenants:seed {--tenant= : slug de um único tenant} {--class= : seeder a executar}';

    protected $description = 'Roda seeders de tenant';

    public function handle(TenantMigrator $migrator): int
    {
        return $this->eachTenant('seeded', fn ($tenant) => $migrator->seed($tenant, $this->option('class')));
    }
}
