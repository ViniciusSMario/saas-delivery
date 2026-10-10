<?php

namespace App\Modules\Exports\Models;

use App\Modules\Access\Models\User;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property string $status pending | done | failed */
class DataExport extends TenantModel
{
    /** Dias que o arquivo fica disponível para baixar. */
    public const KEEP_DAYS = 7;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'completed_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'done' && $this->completed_at?->gt(now()->subDays(self::KEEP_DAYS));
    }
}
