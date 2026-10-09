<?php

namespace App\Modules\Stock\Models;

use App\Support\Money;
use App\Support\Quantity;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Insumo. O saldo (quantity) nunca é editado direto: só muda junto com uma
 * linha em stock_movements (ver StockLedger).
 *
 * @property string $quantity numeric(14,3) como string
 */
class StockItem extends TenantModel
{
    protected $fillable = ['name', 'unit_id', 'min_quantity', 'is_active'];

    protected $attributes = ['quantity' => '0.000', 'min_quantity' => '0.000', 'average_cost_cents' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'min_quantity' => 'decimal:3',
            'average_cost_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    public function scopeBelowMinimum(Builder $query): void
    {
        $query->where('is_active', true)->whereColumn('quantity', '<', 'min_quantity');
    }

    public function isBelowMinimum(): bool
    {
        return Quantity::compare($this->quantity, $this->min_quantity) < 0;
    }

    public function averageCost(): Money
    {
        return Money::cents($this->average_cost_cents);
    }

    /** Valor do saldo em estoque (saldo × custo médio). Saldo negativo vale zero. */
    public function stockValue(): Money
    {
        if (! Quantity::isPositive($this->quantity)) {
            return Money::zero();
        }

        return Money::cents((int) Quantity::round(Quantity::mul($this->quantity, (string) $this->average_cost_cents, 6)));
    }
}
