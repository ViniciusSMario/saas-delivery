<?php

namespace App\Modules\Cash\Models;

use App\Modules\Access\Models\User;
use App\Modules\Cash\MovementType;
use App\Modules\Orders\Models\PaymentMethod;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends TenantModel
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opening_cents' => MoneyCast::class,
            'difference_cents' => MoneyCast::class,
            'expected' => 'array',
            'counted' => 'array',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class)->orderBy('id');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', 'open');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Saldo esperado por forma de pagamento (código => Money), a partir do livro.
     *
     * @return array<string, Money>
     */
    public function expectedByMethod(): array
    {
        $methods = PaymentMethod::orderBy('position')->get()->keyBy('id');
        $totals = [];

        foreach ($methods as $method) {
            $totals[$method->code] = Money::zero();
        }

        foreach ($this->movements()->get() as $movement) {
            $code = $methods[$movement->payment_method_id]->code;
            $delta = Money::cents($movement->amount_cents->cents * $movement->type->sign());
            $totals[$code] = $totals[$code]->add($delta);
        }

        return $totals;
    }

    /** @return array<string, Money> soma por tipo de movimento */
    public function totalsByType(): array
    {
        $totals = array_fill_keys(array_column(MovementType::cases(), 'value'), Money::zero());

        foreach ($this->movements()->get() as $movement) {
            $totals[$movement->type->value] = $totals[$movement->type->value]->add($movement->amount_cents);
        }

        return $totals;
    }
}
