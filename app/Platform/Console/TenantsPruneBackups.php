<?php

namespace App\Platform\Console;

use App\Platform\Backups\TenantBackups;
use Illuminate\Console\Command;

class TenantsPruneBackups extends Command
{
    protected $signature = 'tenants:prune-backups';

    protected $description = 'Apaga backups fora do prazo de retenção (config/platform.php)';

    public function handle(TenantBackups $backups): int
    {
        $this->components->info($backups->prune().' backup(s) apagado(s).');

        return self::SUCCESS;
    }
}
