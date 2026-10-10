<?php

namespace App\Platform\Backups;

use App\Platform\Models\TenantBackup;
use App\Tenancy\Enums\TenantStatus;
use App\Tenancy\Models\Tenant;
use App\Tenancy\SchemaManager;
use App\Tenancy\TenantMigrator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Backup e restauração de um estabelecimento. O banco vai por pg_dump só do schema do
 * tenant (formato custom); os arquivos (fotos, logo) num tar.gz. Cada backup é uma pasta
 * no disco "backups", registrada em public.tenant_backups.
 */
class TenantBackups
{
    public function __construct(
        private readonly SchemaManager $schemas,
        private readonly TenantMigrator $migrator,
    ) {
    }

    public function create(Tenant $tenant, string $kind = 'manual', ?string $actor = null): TenantBackup
    {
        $backup = TenantBackup::create([
            'tenant_id' => $tenant->id,
            'kind' => $kind,
            'status' => 'running',
            'schema_version' => $tenant->schema_version,
            'created_by' => $actor,
        ]);

        $dir = $tenant->id.'/'.now()->format('Ymd_His').'_'.$backup->id.'_'.$kind;

        try {
            $this->disk()->makeDirectory($dir);
            $this->dump($tenant, $this->absolute($dir.'/database.dump'));
            $this->archiveFiles($tenant, $this->absolute($dir.'/files.tar.gz'));

            $this->disk()->put($dir.'/manifest.json', json_encode([
                'tenant_id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'schema' => $tenant->schema_name,
                'schema_version' => $tenant->schema_version,
                'kind' => $kind,
                'created_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $size = collect($this->disk()->files($dir))->sum(fn (string $file) => $this->disk()->size($file));

            $backup->update(['status' => 'done', 'path' => $dir, 'size_bytes' => $size, 'completed_at' => now()]);
            $tenant->recordEvent('backup_created', ['backup' => $backup->id, 'kind' => $kind], $actor);
        } catch (Throwable $e) {
            $this->disk()->deleteDirectory($dir);
            $backup->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000), 'completed_at' => now()]);
            $tenant->recordEvent('backup_failed', ['backup' => $backup->id, 'error' => mb_substr($e->getMessage(), 0, 300)], $actor);

            throw $e;
        }

        return $backup->refresh();
    }

    /**
     * Volta o estabelecimento ao estado do backup. Antes, salva o estado atual
     * (backup "antes da restauração"), então dá para desfazer restaurando esse.
     */
    public function restore(TenantBackup $backup, ?string $actor = null): void
    {
        $tenant = $backup->tenant;

        if ($backup->status !== 'done' || ! $this->disk()->exists($backup->path.'/database.dump')) {
            throw new RuntimeException('Backup indisponível para restauração.');
        }

        if (! in_array($tenant->status, [TenantStatus::Active, TenantStatus::Suspended], true)) {
            throw new RuntimeException("Só dá para restaurar um estabelecimento ativo ou suspenso (status atual: {$tenant->status->value}).");
        }

        $this->create($tenant, 'pre_restore', $actor);

        $previousStatus = $tenant->status;
        $schema = $tenant->schema_name;
        $parked = substr($schema, 0, 26).'r'.now()->format('ymdHis'); // tenant_ + 19 + r + 12: cabe na regex
        $filesDir = storage_path('app/tenants/'.$tenant->id);
        $parkedFiles = $filesDir.'.restore-'.now()->format('YmdHis');

        // Em manutenção: o app e o cardápio respondem "em manutenção" até o fim.
        $tenant->update(['status' => TenantStatus::Restoring]);
        $tenant->recordEvent('restore_started', ['backup' => $backup->id], $actor);

        try {
            $this->schemas->rename($schema, $parked);

            try {
                $this->pgRestore($this->absolute($backup->path.'/database.dump'));
            } catch (Throwable $e) {
                $this->schemas->drop($schema);
                $this->schemas->rename($parked, $schema);

                throw $e;
            }

            if (File::isDirectory($filesDir)) {
                File::moveDirectory($filesDir, $parkedFiles);
            }

            $this->extractFiles($tenant, $this->absolute($backup->path.'/files.tar.gz'));
        } catch (Throwable $e) {
            $tenant->update(['status' => $previousStatus]);
            $tenant->recordEvent('restore_failed', ['backup' => $backup->id, 'error' => mb_substr($e->getMessage(), 0, 300)], $actor);

            throw $e;
        }

        // O estado anterior está no backup "antes da restauração": o que foi tirado do caminho sai.
        $this->schemas->drop($parked);
        File::deleteDirectory($parkedFiles);

        // Backup antigo pode estar numa versão anterior do schema.
        $tenant->update(['status' => $previousStatus]);
        $this->migrator->migrate($tenant);
        $tenant->recordEvent('restored', ['backup' => $backup->id, 'from' => $backup->created_at->toIso8601String()], $actor);
    }

    public function delete(TenantBackup $backup): void
    {
        if ($backup->path) {
            $this->disk()->deleteDirectory($backup->path);
        }

        $backup->delete();
    }

    /**
     * Retenção: os N diários mais recentes, o de domingo das últimas N semanas e os
     * demais (manual, antes da restauração, final) por alguns dias. Falhas saem depois de 7 dias.
     *
     * @return int quantidade apagada
     */
    public function prune(?Carbon $now = null): int
    {
        $now ??= now();
        $config = config('platform.backups');
        $deleted = 0;

        foreach (TenantBackup::query()->orderByDesc('created_at')->orderByDesc('id')->get()->groupBy('tenant_id') as $backups) {
            $daily = $backups->where('kind', 'daily')->where('status', 'done')->values();
            $keep = $daily->take($config['keep_daily'])->pluck('id')
                ->merge($daily->filter(fn ($b) => $b->created_at->isSunday() && $b->created_at->gte($now->copy()->subWeeks($config['keep_weekly'])))->pluck('id'));

            foreach ($backups as $backup) {
                $expired = match (true) {
                    $backup->status === 'running' => false,
                    $backup->status === 'failed' => $backup->created_at->lt($now->copy()->subDays(7)),
                    $backup->kind === 'daily' => ! $keep->contains($backup->id),
                    default => $backup->created_at->lt($now->copy()->subDays($config['keep_other_days'])),
                };

                if ($expired) {
                    $this->delete($backup);
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    public function absolute(string $path): string
    {
        return $this->disk()->path($path);
    }

    public function disk(): Filesystem
    {
        return Storage::disk('backups');
    }

    private function dump(Tenant $tenant, string $file): void
    {
        $this->run([
            'pg_dump', '--format=custom', '--no-owner', '--no-privileges',
            '--schema='.$tenant->schema_name, '--file='.$file,
            ...$this->connectionArgs(),
        ]);
    }

    private function pgRestore(string $file): void
    {
        $this->run([
            'pg_restore', '--no-owner', '--no-privileges', '--exit-on-error', '--single-transaction',
            ...$this->connectionArgs(), $file,
        ]);
    }

    private function archiveFiles(Tenant $tenant, string $file): void
    {
        $root = storage_path('app/tenants');
        File::ensureDirectoryExists($root.'/'.$tenant->id);

        $this->run(['tar', '-czf', $file, '-C', $root, $tenant->id]);
    }

    private function extractFiles(Tenant $tenant, string $file): void
    {
        File::ensureDirectoryExists(storage_path('app/tenants'));

        if (is_file($file)) {
            $this->run(['tar', '-xzf', $file, '-C', storage_path('app/tenants')]);
        }
    }

    /** @return list<string> */
    private function connectionArgs(): array
    {
        $db = config('database.connections.central');

        return ['--host='.$db['host'], '--port='.$db['port'], '--username='.$db['username'], '--dbname='.$db['database']];
    }

    private function run(array $command): void
    {
        $result = Process::timeout(config('platform.backups.timeout'))
            ->env(['PGPASSWORD' => (string) config('database.connections.central.password')])
            ->run($command);

        if ($result->failed()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'Falha ao executar '.$command[0].'.');
        }
    }
}
