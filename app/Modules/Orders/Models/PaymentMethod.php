<?php

namespace App\Modules\Orders\Models;

use App\Modules\Finance\Models\FinancialAccount;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends TenantModel
{
    protected $fillable = ['code', 'name', 'is_active', 'accepts_change', 'position', 'financial_account_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'accepts_change' => 'boolean'];
    }

    /** Conta financeira onde o dinheiro desta forma de pagamento cai. */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public const DEFAULTS = [
        'cash' => ['Dinheiro', true],
        'pix' => ['Pix', false],
        'credit' => ['Cartão de crédito', false],
        'debit' => ['Cartão de débito', false],
        'meal_voucher' => ['Vale-refeição', false],
    ];

    public static function seedDefaults(): void
    {
        $position = 0;

        foreach (self::DEFAULTS as $code => [$name, $acceptsChange]) {
            static::firstOrCreate(['code' => $code], [
                'name' => $name,
                'accepts_change' => $acceptsChange,
                'position' => $position++,
            ]);
        }
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position');
    }
}
