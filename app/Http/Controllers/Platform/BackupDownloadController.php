<?php

namespace App\Http\Controllers\Platform;

use App\Platform\Backups\TenantBackups;
use App\Platform\Models\TenantBackup;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/** Baixa um backup como .zip (dump, arquivos e manifesto). Só administradores: tem dados pessoais. */
class BackupDownloadController
{
    public function __invoke(TenantBackup $backup, TenantBackups $backups): BinaryFileResponse
    {
        Gate::authorize('platform.manage-tenants');
        abort_unless($backup->status === 'done' && $backup->path && $backups->disk()->exists($backup->path), 404);

        $zipPath = tempnam(sys_get_temp_dir(), 'backup').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($backups->disk()->files($backup->path) as $file) {
            $zip->addFile($backups->absolute($file), basename($file));
        }

        $zip->close();

        $tenant = $backup->tenant;
        $backup->tenant->recordEvent('backup_downloaded', ['backup' => $backup->id], auth('platform')->user()->email);
        $name = ($tenant->slug ?? 'tenant').'-'.$backup->created_at->format('Ymd-His').'-'.$backup->kind.'.zip';

        return response()->download($zipPath, $name)->deleteFileAfterSend();
    }
}
