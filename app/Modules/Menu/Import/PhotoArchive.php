<?php

namespace App\Modules\Menu\Import;

use RuntimeException;
use ZipArchive;

/**
 * .zip com as fotos dos produtos, achadas pelo nome do arquivo (sem a extensão).
 * Só JPG, PNG e WebP de até 4 MB; pastas e arquivos de sistema (__MACOSX, .DS_Store) são ignorados.
 */
class PhotoArchive
{
    public const MAX_FILES = 500;

    public const MAX_BYTES = 4 * 1024 * 1024;

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private ZipArchive $zip;

    /** @var array<string, int> nome normalizado => índice no zip */
    private array $index = [];

    /** @var list<string> arquivos ignorados (tipo ou tamanho) */
    public array $ignored = [];

    public function __construct(string $path)
    {
        $this->zip = new ZipArchive;

        if ($this->zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Não conseguimos abrir o arquivo de fotos. Envie um .zip.');
        }

        if ($this->zip->numFiles > self::MAX_FILES * 2) {
            throw new RuntimeException('O .zip tem arquivos demais (até '.self::MAX_FILES.' fotos).');
        }

        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $stat = $this->zip->statIndex($i);
            $name = $stat['name'] ?? '';
            $base = basename($name);

            if (str_ends_with($name, '/') || str_starts_with($name, '__MACOSX/') || str_starts_with($base, '.') || $base === '') {
                continue;
            }

            $extension = strtolower(pathinfo($base, PATHINFO_EXTENSION));

            if (! in_array($extension, self::EXTENSIONS, true) || ($stat['size'] ?? 0) > self::MAX_BYTES || ($stat['size'] ?? 0) === 0) {
                $this->ignored[] = $base;

                continue;
            }

            $this->index[self::key($base)] ??= $i;
        }

        if (count($this->index) > self::MAX_FILES) {
            throw new RuntimeException('O .zip tem fotos demais (até '.self::MAX_FILES.').');
        }
    }

    /** "Pizza Margherita.JPG" → "pizza margherita". */
    public static function key(string $name): string
    {
        return MenuSpreadsheet::plain(preg_replace('/\.(jpe?g|png|webp)$/i', '', basename($name)));
    }

    public function has(?string $name): bool
    {
        return $name !== null && $name !== '' && isset($this->index[self::key($name)]);
    }

    /** @return list<string> nomes (normalizados) de todas as fotos do zip */
    public function names(): array
    {
        return array_keys($this->index);
    }

    /**
     * Conteúdo e extensão da foto, conferindo que é mesmo uma imagem.
     *
     * @return array{0: string, 1: string}|null
     */
    public function read(string $name): ?array
    {
        $i = $this->index[self::key($name)] ?? null;
        $bytes = $i !== null ? $this->zip->getFromIndex($i, self::MAX_BYTES + 1) : false;

        if ($bytes === false || strlen($bytes) > self::MAX_BYTES) {
            return null;
        }

        $info = @getimagesizefromstring($bytes);
        $extension = $info ? (self::MIMES[$info['mime']] ?? null) : null;

        return $extension ? [$bytes, $extension] : null;
    }

    public function close(): void
    {
        $this->zip->close();
    }
}
