<?php

namespace App\Modules\Printing\Models;

use App\Modules\Orders\Models\Order;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um cupom na fila de uma impressora. lines: [{text, style: normal|bold|big, align: left|center}].
 *
 * @property string $status pending | printing | printed | failed
 */
class PrintJob extends TenantModel
{
    /** Cupom "pegado" por uma estação que sumiu (aba fechada) volta para a fila depois disso. */
    public const CLAIM_TIMEOUT_SECONDS = 120;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'lines' => 'array',
            'attempts' => 'integer',
            'claimed_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(PrintStation::class, 'print_station_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Na fila: pendente, ou "pegado" há tempo demais sem confirmação. */
    public function scopeWaiting(Builder $query): void
    {
        $query->where(fn ($q) => $q->where('status', 'pending')
            ->orWhere(fn ($q) => $q->where('status', 'printing')->where('claimed_at', '<', now()->subSeconds(self::CLAIM_TIMEOUT_SECONDS))));
    }
}
