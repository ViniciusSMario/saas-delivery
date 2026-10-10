<?php

namespace App\Platform\Models;

use App\Support\Money;
use App\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plano de assinatura. limits: {users, tables, products, orders_per_month} (null = ilimitado).
 * features: {stock, finance} (ausente = liberado).
 */
class Plan extends Model
{
    /** [quando tem limite, quando é ilimitado] */
    public const LIMIT_LABELS = [
        'users' => ['usuários', 'Usuários ilimitados'],
        'tables' => ['mesas', 'Mesas ilimitadas'],
        'products' => ['produtos', 'Produtos ilimitados'],
        'orders_per_month' => ['pedidos por mês', 'Pedidos ilimitados'],
    ];

    public const FEATURE_LABELS = [
        'stock' => 'Estoque e ficha técnica',
        'finance' => 'Financeiro (contas a pagar e receber)',
    ];

    protected $connection = 'central';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'features' => 'array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_public', true)->where('is_active', true)->orderBy('sort')->orderBy('price_cents');
    }

    public function price(): Money
    {
        return Money::cents((int) $this->price_cents);
    }

    public function limit(string $key): ?int
    {
        $value = $this->limits[$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? true);
    }

    /** @return list<string> linhas para a vitrine de planos */
    public function highlights(): array
    {
        $lines = [];

        foreach (self::LIMIT_LABELS as $key => [$label, $unlimited]) {
            $value = $this->limit($key);
            $lines[] = $value === null ? $unlimited : "Até {$value} {$label}";
        }

        foreach (self::FEATURE_LABELS as $key => $label) {
            if ($this->hasFeature($key)) {
                $lines[] = $label;
            }
        }

        return $lines;
    }
}
