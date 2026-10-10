<?php

namespace App\Platform\Backups;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Cópia dos backups fora do servidor (S3, Backblaze B2, Cloudflare R2, Wasabi...).
 * Liga com BACKUPS_OFFSITE_DISK=s3 e as variáveis AWS_*; desligado, nada muda.
 * Os caminhos no bucket são os mesmos do disco local "backups".
 */
class Offsite
{
    public function enabled(): bool
    {
        return (string) config('platform.backups.offsite_disk') !== '';
    }

    public function disk(): Filesystem
    {
        return Storage::disk(config('platform.backups.offsite_disk'));
    }

    /** Envia uma pasta (ou um arquivo) do disco local para o bucket. */
    public function upload(string $path): void
    {
        $local = Storage::disk('backups');
        $files = $local->directoryExists($path) ? $local->allFiles($path) : [$path];

        foreach ($files as $file) {
            $stream = $local->readStream($file);
            $this->disk()->writeStream($file, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function delete(string $path): void
    {
        $this->disk()->directoryExists($path) ? $this->disk()->deleteDirectory($path) : $this->disk()->delete($path);
    }

    /** Traz do bucket o que não está mais no servidor (ex.: restaurar num servidor novo). */
    public function download(string $path): bool
    {
        $files = $this->disk()->allFiles($path);

        foreach ($files as $file) {
            $stream = $this->disk()->readStream($file);
            Storage::disk('backups')->writeStream($file, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $files !== [];
    }
}
