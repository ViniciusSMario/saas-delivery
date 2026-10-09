<?php

namespace App\Modules\Finance\Models;

use App\Modules\Access\Models\User;
use App\Support\Casts\MoneyCast;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Conta a pagar (payable) ou a receber (receivable). */
class FinancialEntry extends TenantModel
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_cents' => MoneyCast::class,
            'due_date' => 'date',
            'paid_on' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'financial_category_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', 'open');
    }

    public function isOverdue(string $today): bool
    {
        return $this->status === 'open' && $this->due_date->toDateString() < $today;
    }

    /** Situação para exibir: Pago, Cancelado, Vencido ou Em aberto. */
    public function statusLabel(string $today): string
    {
        return match (true) {
            $this->status === 'paid' => 'Pago',
            $this->status === 'cancelled' => 'Cancelado',
            $this->isOverdue($today) => 'Vencido',
            default => 'Em aberto',
        };
    }

    public function kindLabel(): string
    {
        return $this->kind === 'payable' ? 'A pagar' : 'A receber';
    }
}
