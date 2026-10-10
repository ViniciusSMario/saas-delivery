<?php

namespace App\Platform\Console;

use App\Platform\Backups\Offsite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Backup do banco inteiro (plataforma + todos os estabelecimentos) num arquivo só,
 * para recuperar tudo se o servidor sumir. Fica em backups/database/ e vai para o bucket.
 */
class PlatformBackupDatabase extends Command
{
    protected $signature = 'platform:backup-database';

    protected $description = 'Faz o backup do banco inteiro (pg_dump) e mantém os mais recentes';

    public function handle(Offsite $offsite): int
    {
        $disk = Storage::disk('backups');
        $path = 'database/database-'.now()->format('Ymd-His').'.dump';
        $disk->makeDirectory('database');
        $db = config('database.connections.central');

        $result = Process::timeout(config('platform.backups.timeout'))
            ->env(['PGPASSWORD' => (string) $db['password']])
            ->run(['pg_dump', '--format=custom', '--no-owner', '--no-privileges', '--file='.$disk->path($path),
                '--host='.$db['host'], '--port='.$db['port'], '--username='.$db['username'], '--dbname='.$db['database']]);

        if ($result->failed()) {
            $disk->delete($path);
            $this->components->error('pg_dump falhou: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        if ($offsite->enabled()) {
            $offsite->upload($path);
        }

        // Retenção: os N mais recentes (no servidor e no bucket).
        $old = collect($disk->files('database'))->sort()->reverse()->slice(config('platform.backups.keep_database'));

        foreach ($old as $file) {
            $disk->delete($file);

            if ($offsite->enabled()) {
                $offsite->delete($file);
            }
        }

        $size = number_format($disk->size($path) / 1024 / 1024, 1, ',', '.');
        $this->components->info("Banco salvo em {$path} ({$size} MB)".($offsite->enabled() ? ', com cópia no bucket.' : '.'));

        return self::SUCCESS;
    }
}
