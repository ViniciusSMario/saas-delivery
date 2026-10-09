<?php

namespace App\Modules\Finance;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\MovementType;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Orders\Models\PaymentMethod;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Contas a pagar e a receber. */
class FinanceBook
{
    /**
     * @param  array{kind: string, description: string, amount: Money, due_date: string, counterparty?: string|null,
     *               category_id?: int|null, recurrence?: string, notes?: string|null, source_ref?: string|null}  $data
     */
    public function create(array $data, ?User $user): FinancialEntry
    {
        $this->assertValid($data);

        $entry = FinancialEntry::create([
            'kind' => $data['kind'],
            'description' => trim($data['description']),
            'counterparty' => isset($data['counterparty']) ? (trim($data['counterparty']) ?: null) : null,
            'financial_category_id' => $data['category_id'] ?? null,
            'amount_cents' => $data['amount'],
            'due_date' => $data['due_date'],
            'status' => 'open',
            'recurrence' => $data['recurrence'] ?? 'none',
            'notes' => $data['notes'] ?? null,
            'source_ref' => $data['source_ref'] ?? null,
            'created_by' => $user?->id,
        ]);

        Audit::record('finance.created', $entry, ['descricao' => $entry->description, 'valor' => $entry->amount_cents->format(), 'vencimento' => $entry->due_date->format('d/m/Y')], $user);

        return $entry;
    }

    public function update(FinancialEntry $entry, array $data, ?User $user): FinancialEntry
    {
        if ($entry->status !== 'open') {
            throw ValidationException::withMessages(['entry' => 'Só lançamentos em aberto podem ser editados.']);
        }

        $this->assertValid($data + ['kind' => $entry->kind]);

        $entry->update([
            'description' => trim($data['description']),
            'counterparty' => isset($data['counterparty']) ? (trim($data['counterparty']) ?: null) : null,
            'financial_category_id' => $data['category_id'] ?? null,
            'amount_cents' => $data['amount'],
            'due_date' => $data['due_date'],
            'recurrence' => $data['recurrence'] ?? 'none',
            'notes' => $data['notes'] ?? null,
        ]);

        Audit::record('finance.updated', $entry, Audit::diff($entry), $user);

        return $entry;
    }

    /** Baixa (pagou/recebeu). Recorrente mensal gera o próximo mês em aberto. */
    public function pay(FinancialEntry $entry, FinancialAccount $account, string $paidOn, ?User $user): FinancialEntry
    {
        return DB::connection('tenant')->transaction(function () use ($entry, $account, $paidOn, $user) {
            $locked = FinancialEntry::lockForUpdate()->findOrFail($entry->id);

            if ($locked->status !== 'open') {
                throw ValidationException::withMessages(['entry' => 'Este lançamento não está em aberto.']);
            }

            $locked->update(['status' => 'paid', 'paid_on' => $paidOn, 'financial_account_id' => $account->id]);

            if ($locked->recurrence === 'monthly') {
                $next = $locked->replicate(['status', 'paid_on', 'financial_account_id', 'source_ref']);
                $next->fill(['status' => 'open', 'due_date' => $locked->due_date->copy()->addMonthNoOverflow()])->save();
            }

            Audit::record('finance.paid', $locked, ['valor' => $locked->amount_cents->format(), 'conta' => $account->name], $user);
            $entry->setRawAttributes($locked->getAttributes(), true);

            return $entry;
        });
    }

    public function cancel(FinancialEntry $entry, ?User $user): void
    {
        if ($entry->status !== 'open') {
            throw ValidationException::withMessages(['entry' => 'Só lançamentos em aberto podem ser cancelados.']);
        }

        $entry->update(['status' => 'cancelled']);
        Audit::record('finance.cancelled', $entry, ['descricao' => $entry->description], $user);
    }

    /**
     * Fechamento do caixa: um recebimento já baixado por forma de pagamento (vendas
     * menos estornos), na conta ligada à forma. Idempotente pelo source_ref.
     */
    public function recordCashSession(CashSession $session): void
    {
        $category = FinancialCategory::firstOrCreate(['name' => 'Vendas', 'kind' => 'receivable']);
        $methods = PaymentMethod::with('financialAccount')->get()->keyBy('id');
        $closedOn = CarbonImmutable::parse($session->closed_at)->setTimezone(Setting::read('timezone', 'America/Sao_Paulo'))->toDateString();
        $net = [];

        foreach ($session->movements()->whereIn('type', [MovementType::Sale, MovementType::Refund])->get() as $movement) {
            $net[$movement->payment_method_id] = ($net[$movement->payment_method_id] ?? 0) + $movement->amount_cents->cents * $movement->type->sign();
        }

        foreach ($net as $methodId => $cents) {
            $method = $methods[$methodId];

            if ($cents <= 0 || ! $method->financialAccount) {
                continue;
            }

            FinancialEntry::firstOrCreate(['source_ref' => "cash_session:{$session->id}:{$method->code}"], [
                'kind' => 'receivable',
                'description' => "Vendas · {$session->register->name} · {$method->name}",
                'financial_category_id' => $category->id,
                'amount_cents' => Money::cents($cents),
                'due_date' => $closedOn,
                'status' => 'paid',
                'paid_on' => $closedOn,
                'financial_account_id' => $method->financialAccount->id,
                'created_by' => $session->closed_by,
            ]);
        }
    }

    private function assertValid(array $data): void
    {
        if (! in_array($data['kind'] ?? null, ['payable', 'receivable'], true)) {
            throw ValidationException::withMessages(['kind' => 'Tipo inválido.']);
        }

        if (trim($data['description'] ?? '') === '') {
            throw ValidationException::withMessages(['description' => 'Informe a descrição.']);
        }

        if (! ($data['amount'] ?? null) instanceof Money || $data['amount']->cents <= 0) {
            throw ValidationException::withMessages(['amount' => 'Informe um valor maior que zero.']);
        }

        if (! in_array($data['recurrence'] ?? 'none', ['none', 'monthly'], true)) {
            throw ValidationException::withMessages(['recurrence' => 'Recorrência inválida.']);
        }

        if (! empty($data['category_id']) && ! FinancialCategory::whereKey($data['category_id'])->where('kind', $data['kind'])->exists()) {
            throw ValidationException::withMessages(['category_id' => 'Categoria inválida.']);
        }
    }
}
