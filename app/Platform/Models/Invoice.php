<?php

namespace App\Platform\Models;

use App\Support\Money;
use App\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Fatura da assinatura de um estabelecimento, espelho da cobrança no gateway. */
class Invoice extends Model
{
    public const STATUS_LABELS = [
        'pending' => ['Em aberto', 'badge-amber'],
        'paid' => ['Paga', 'badge-green'],
        'overdue' => ['Vencida', 'badge-red'],
        'canceled' => ['Cancelada', 'badge-gray'],
    ];

    protected $connection = 'central';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', ['pending', 'overdue']);
    }

    public function amount(): Money
    {
        return Money::cents((int) $this->amount_cents);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status][0] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUS_LABELS[$this->status][1] ?? 'badge-gray';
    }
}
