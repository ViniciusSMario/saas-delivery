<?php

namespace App\Tenancy\Console;

use App\Tenancy\Models\Tenant;
use App\Tenancy\SchemaManager;

/**
 * Cria/ajusta o papel do PostgreSQL de cada estabelecimento (TENANCY_DATABASE_ROLES):
 * dono do schema e de tudo nele. Idempotente; roda no deploy. Também remove papéis
 * de schemas que não existem mais.
 */
class TenantsRoles extends TenantCommand
{
    protected $signature = 'tenants:roles {--tenant= : slug (padrão: todos)}';

    protected $description = 'Cria ou ajusta o papel do PostgreSQL de cada estabelecimento';

    public function handle(SchemaManager $schemas): int
    {
        $result = $this->eachTenant('roles_synced', function (Tenant $tenant) use ($schemas) {
            $schemas->assignToRole($tenant->schema_name);

            return SchemaManager::roleName($tenant->schema_name);
        });

        if (! $this->option('tenant')) {
            foreach ($schemas->orphanRoles() as $role) {
                $schemas->dropOrphanRole($role);
                $this->components->info("Papel sem schema removido: {$role}");
            }
        }

        return $result;
    }
}
