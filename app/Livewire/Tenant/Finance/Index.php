<?php

namespace App\Livewire\Tenant\Finance;

use App\Modules\Establishment\Models\Setting;
use App\Modules\Finance\FinanceBook;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialEntry;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Financeiro')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $kind = '';

    #[Url]
    public string $status = 'open';

    #[Url]
    public string $month = '';

    // Baixa
    public ?int $payingId = null;

    public ?int $accountId = null;

    public string $paidOn = '';

    public function mount(): void
    {
        $this->month = $this->month ?: $this->today()->format('Y-m');
    }

    public function updated($property): void
    {
        if (in_array($property, ['kind', 'status', 'month'], true)) {
            $this->resetPage();
        }
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(Setting::read('timezone', 'America/Sao_Paulo'));
    }

    public function startPay(int $id): void
    {
        $this->payingId = $id;
        $this->paidOn = $this->today()->toDateString();
        $this->accountId = FinancialAccount::where('is_active', true)->where('type', 'bank')->value('id') ?? FinancialAccount::where('is_active', true)->value('id');
        $this->resetErrorBag();
    }

    public function pay(FinanceBook $book): void
    {
        $this->authorize('finance.manage');
        $this->validate(['accountId' => ['required', 'integer'], 'paidOn' => ['required', 'date']], attributes: ['accountId' => 'conta', 'paidOn' => 'data']);

        try {
            $book->pay(FinancialEntry::findOrFail($this->payingId), FinancialAccount::findOrFail($this->accountId), $this->paidOn, Auth::guard('web')->user());
        } catch (ValidationException $e) {
            $this->addError('accountId', collect($e->errors())->flatten()->first());

            return;
        }

        $this->payingId = null;
    }

    public function cancel(int $id, FinanceBook $book): void
    {
        $this->authorize('finance.manage');
        $book->cancel(FinancialEntry::findOrFail($id), Auth::guard('web')->user());
    }

    public function render()
    {
        $today = $this->today()->toDateString();
        $in7 = $this->today()->addDays(7)->toDateString();
        $month = rescue(fn () => CarbonImmutable::createFromFormat('Y-m', $this->month)->startOfMonth(), $this->today()->startOfMonth(), false);

        $sum = fn ($query) => Money::cents((int) $query->sum('amount_cents'));

        $entries = FinancialEntry::with(['category', 'account'])
            ->when($this->kind !== '', fn ($q) => $q->where('kind', $this->kind))
            ->when($this->status === 'open', fn ($q) => $q->where('status', 'open'))
            ->when($this->status === 'overdue', fn ($q) => $q->where('status', 'open')->where('due_date', '<', $today))
            ->when($this->status === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->when($this->status !== 'overdue', fn ($q) => $q->whereBetween($this->status === 'paid' ? 'paid_on' : 'due_date', [$month->toDateString(), $month->endOfMonth()->toDateString()]))
            ->orderBy($this->status === 'paid' ? 'paid_on' : 'due_date')
            ->orderBy('id')
            ->paginate(30);

        return view('livewire.tenant.finance.index', [
            'entries' => $entries,
            'today' => $today,
            'accounts' => FinancialAccount::where('is_active', true)->orderBy('name')->get(),
            'overduePayable' => $sum(FinancialEntry::open()->where('kind', 'payable')->where('due_date', '<', $today)),
            'next7Payable' => $sum(FinancialEntry::open()->where('kind', 'payable')->whereBetween('due_date', [$today, $in7])),
            'openReceivable' => $sum(FinancialEntry::open()->where('kind', 'receivable')),
            'monthIn' => $sum(FinancialEntry::where('status', 'paid')->where('kind', 'receivable')->whereBetween('paid_on', [$month->toDateString(), $month->endOfMonth()->toDateString()])),
            'monthOut' => $sum(FinancialEntry::where('status', 'paid')->where('kind', 'payable')->whereBetween('paid_on', [$month->toDateString(), $month->endOfMonth()->toDateString()])),
            'paying' => $this->payingId ? FinancialEntry::find($this->payingId) : null,
        ]);
    }
}
