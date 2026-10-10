<?php

namespace App\Platform\Models;

use App\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um backup de estabelecimento: pasta em backups/<tenant>/<data>_<tipo>/ com
 * database.dump (pg_dump do schema), files.tar.gz (fotos e logo) e manifest.json.
 *
 * @property string $kind daily | manual | pre_restore | final
 * @property string $status running | done | failed
 */
class TenantBackup extends Model
{
    public const UPDATED_AT = null;

    public const KINDS = [
        'daily' => 'Diário',
        'manual' => 'Manual',
        'pre_restore' => 'Antes da restauração',
        'final' => 'Final (exclusão)',
    ];

    protected $connection = 'central';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    public function scopeDone(Builder $query): void
    {
        $query->where('status', 'done');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function humanSize(): string
    {
        $bytes = $this->size_bytes;

        return match (true) {
            $bytes >= 1024 ** 3 => number_format($bytes / 1024 ** 3, 1, ',', '.').' GB',
            $bytes >= 1024 ** 2 => number_format($bytes / 1024 ** 2, 1, ',', '.').' MB',
            default => number_format(max(1, $bytes) / 1024, 0, ',', '.').' KB',
        };
    }
}
