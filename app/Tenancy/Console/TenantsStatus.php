<?php

namespace App\Tenancy\Console;

use App\Tenancy\Enums\TenantStatus;
use App\Tenancy\Models\Tenant;
use App\Tenancy\TenantMigrator;
use Illuminate\Console\Command;
use Throwable;

class TenantsStatus extends Command
{
    protected $signature = 'tenants:status';

    protected $description = 'Mostra status e versão de schema de cada tenant';

    public function handle(TenantMigrator $migrator): int
    {
        $rows = Tenant::query()->with('domains')->orderBy('created_at')->get()->map(function (Tenant $tenant) use ($migrator) {
            $pending = '-';

            if (in_array($tenant->status, TenantStatus::migratable(), true)) {
                try {
                    $pending = (string) count($migrator->pending($tenant));
                } catch (Throwable $e) {
                    $pending = 'erro: '.$e->getMessage();
                }
            }

            return [
                $tenant->slug,
                $tenant->status->value,
                $tenant->schema_name,
                $tenant->domains->pluck('domain')->implode(', '),
                $tenant->schema_version ?? '-',
                $pending,
            ];
        });

        $this->table(['slug', 'status', 'schema', 'domínios', 'schema_version', 'pendentes'], $rows);

        return self::SUCCESS;
    }
}
