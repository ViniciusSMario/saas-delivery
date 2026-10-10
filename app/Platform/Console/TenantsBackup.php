<?php

namespace App\Platform\Console;

use App\Platform\Backups\TenantBackups;
use App\Tenancy\Console\TenantCommand;
use App\Tenancy\Models\Tenant;

/** Backup de um ou de todos os estabelecimentos ativos/suspensos (agendado todo dia). */
class TenantsBackup extends TenantCommand
{
    protected $signature = 'tenants:backup {--tenant= : slug (padrão: todos)} {--kind=manual : daily ou manual}';

    protected $description = 'Faz backup do schema e dos arquivos de cada estabelecimento';

    public function handle(TenantBackups $backups): int
    {
        $kind = $this->option('kind') === 'daily' ? 'daily' : 'manual';

        return $this->eachTenant('backup_command', function (Tenant $tenant) use ($backups, $kind) {
            $backup = $backups->create($tenant, $kind, 'console');

            return "backup #{$backup->id} ({$backup->humanSize()})";
        });
    }
}
