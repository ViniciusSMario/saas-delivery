<?php

namespace App\Platform\Console;

use App\Platform\Actions\TenantDeletion;
use App\Tenancy\Enums\TenantStatus;
use App\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

/** Remove de vez os estabelecimentos cuja carência de exclusão acabou (agendado todo dia). */
class TenantsPurgeDeleted extends Command
{
    protected $signature = 'tenants:purge-deleted';

    protected $description = 'Apaga definitivamente os estabelecimentos com exclusão vencida (com backup final)';

    public function handle(TenantDeletion $deletion): int
    {
        $tenants = Tenant::where('status', TenantStatus::PendingDeletion)
            ->where('deletion_scheduled_at', '<=', now())
            ->get();

        $failed = false;

        foreach ($tenants as $tenant) {
            try {
                $deletion->purge($tenant);
                $this->components->info("{$tenant->slug} removido.");
            } catch (Throwable $e) {
                $failed = true;
                $this->components->error("{$tenant->slug}: {$e->getMessage()}");
                $tenant->recordEvent('purge_failed', ['error' => mb_substr($e->getMessage(), 0, 300)]);
            }
        }

        if ($tenants->isEmpty()) {
            $this->components->info('Nada para remover.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
