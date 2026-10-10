<?php

namespace App\Platform\Console;

use App\Platform\Backups\TenantBackups;
use App\Platform\Models\TenantBackup;
use App\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

/** Restaura um estabelecimento a partir de um backup (sem --backup, lista os disponíveis). */
class TenantsRestore extends Command
{
    protected $signature = 'tenants:restore {tenant : slug} {--backup= : id do backup} {--force : não pedir confirmação}';

    protected $description = 'Restaura o schema e os arquivos de um estabelecimento a partir de um backup';

    public function handle(TenantBackups $backups): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->first();

        if (! $tenant) {
            $this->components->error('Tenant não encontrado.');

            return self::FAILURE;
        }

        $available = TenantBackup::where('tenant_id', $tenant->id)->done()->latest('created_at')->latest('id')->get();

        if (! $this->option('backup')) {
            $this->table(['id', 'tipo', 'data', 'tamanho', 'schema_version'], $available->map(fn (TenantBackup $b) => [
                $b->id, $b->kindLabel(), $b->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i'), $b->humanSize(), $b->schema_version ?? '-',
            ]));
            $this->line('Use --backup=<id> para restaurar.');

            return self::SUCCESS;
        }

        $backup = $available->firstWhere('id', (int) $this->option('backup'));

        if (! $backup) {
            $this->components->error('Backup não encontrado para este tenant.');

            return self::FAILURE;
        }

        $when = $backup->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i');

        if (! $this->option('force') && ! $this->confirm("Restaurar {$tenant->slug} para {$when}? O estado atual vira um backup \"antes da restauração\".")) {
            return self::FAILURE;
        }

        try {
            $backups->restore($backup, 'console');
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("{$tenant->slug} restaurado para {$when}.");

        return self::SUCCESS;
    }
}
