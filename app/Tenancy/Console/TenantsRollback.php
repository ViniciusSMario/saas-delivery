<?php

namespace App\Tenancy\Console;

use App\Tenancy\TenantMigrator;

class TenantsRollback extends TenantCommand
{
    protected $signature = 'tenants:rollback {--tenant= : slug de um único tenant} {--step=1 : quantas migrations desfazer}';

    protected $description = 'Desfaz migrations de tenant';

    public function handle(TenantMigrator $migrator): int
    {
        return $this->eachTenant('rolled_back', fn ($tenant) => $migrator->rollback($tenant, (int) $this->option('step')));
    }
}
