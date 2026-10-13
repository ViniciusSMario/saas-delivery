<?php

namespace App\Livewire\Tenant\Finance;

use App\Livewire\Tenant\Concerns\Toasts;
use App\Modules\Access\Audit;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Orders\Models\PaymentMethod;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Contas financeiras e para qual conta vai cada forma de pagamento. */
#[Title('Contas financeiras')]
class Accounts extends Component
{
    use Toasts;

    public string $name = '';

    public string $type = 'bank';

    public string $openingBalance = '0,00';

    /** @var array<int, int|string|null> payment_method_id => financial_account_id */
    public array $mapping = [];

    public function mount(): void
    {
        $this->mapping = PaymentMethod::pluck('financial_account_id', 'id')->all();
    }

    public function create(): void
    {
        $this->authorize('finance.manage');
        $this->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('tenant.financial_accounts', 'name')],
            'type' => ['required', Rule::in(array_keys(FinancialAccount::TYPES))],
            'openingBalance' => ['required', fn ($a, $v, $fail) => Money::isValid($v) ? null : $fail('Valor inválido.')],
        ], attributes: ['name' => 'nome', 'openingBalance' => 'saldo inicial']);

        $account = FinancialAccount::create(['name' => trim($this->name), 'type' => $this->type, 'opening_balance_cents' => Money::parse($this->openingBalance)]);
        Audit::record('finance.account_created', $account, ['conta' => $account->name]);
        $this->reset('name', 'openingBalance');
    }

    public function saveMapping(): void
    {
        $this->authorize('finance.manage');

        foreach (PaymentMethod::all() as $method) {
            $accountId = $this->mapping[$method->id] ?? null;
            $method->update(['financial_account_id' => $accountId ? (int) $accountId : null]);
        }

        $this->toast('Destino das formas de pagamento salvo.');
    }

    public function render()
    {
        return view('livewire.tenant.finance.accounts', [
            'accounts' => FinancialAccount::orderBy('name')->get(),
            'methods' => PaymentMethod::orderBy('position')->get(),
            'types' => FinancialAccount::TYPES,
        ]);
    }
}
