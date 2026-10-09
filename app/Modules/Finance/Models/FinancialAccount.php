<?php

namespace App\Modules\Finance\Models;

use App\Modules\Orders\Models\PaymentMethod;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends TenantModel
{
    public const TYPES = ['cash' => 'Dinheiro (caixa da loja)', 'bank' => 'Banco / Pix', 'card' => 'Maquininha / cartão', 'other' => 'Outra'];

    protected $fillable = ['name', 'type', 'opening_balance_cents', 'is_active'];

    protected $attributes = ['opening_balance_cents' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return ['opening_balance_cents' => MoneyCast::class, 'is_active' => 'boolean'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(FinancialEntry::class);
    }

    /** Saldo = inicial + recebido - pago (só lançamentos baixados nesta conta). */
    public function balance(): Money
    {
        $sums = $this->entries()->where('status', 'paid')
            ->selectRaw("coalesce(sum(case when kind = 'receivable' then amount_cents else -amount_cents end), 0) as net")
            ->value('net');

        return $this->opening_balance_cents->add(Money::cents((int) $sums));
    }

    public static function seedDefaults(): void
    {
        if (static::exists()) {
            return;
        }

        $cash = static::create(['name' => 'Caixa da loja', 'type' => 'cash']);
        $bank = static::create(['name' => 'Banco / Pix', 'type' => 'bank']);
        $card = static::create(['name' => 'Maquininha', 'type' => 'card']);

        $map = ['cash' => $cash, 'pix' => $bank, 'credit' => $card, 'debit' => $card, 'meal_voucher' => $card];

        foreach ($map as $code => $account) {
            PaymentMethod::where('code', $code)->whereNull('financial_account_id')->update(['financial_account_id' => $account->id]);
        }
    }
}
