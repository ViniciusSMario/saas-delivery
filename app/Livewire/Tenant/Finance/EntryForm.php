<?php

namespace App\Livewire\Tenant\Finance;

use App\Modules\Establishment\Models\Setting;
use App\Modules\Finance\FinanceBook;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Finance\Models\FinancialEntry;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EntryForm extends Component
{
    #[Locked]
    public ?int $entryId = null;

    public string $kind = 'payable';

    public string $description = '';

    public string $counterparty = '';

    public ?int $categoryId = null;

    public string $amount = '';

    public string $dueDate = '';

    public bool $monthly = false;

    public string $notes = '';

    public function mount(?FinancialEntry $entry = null): void
    {
        if ($entry?->exists) {
            abort_unless($entry->status === 'open', 403, 'Só lançamentos em aberto podem ser editados.');
            $this->entryId = $entry->id;
            $this->kind = $entry->kind;
            $this->description = $entry->description;
            $this->counterparty = (string) $entry->counterparty;
            $this->categoryId = $entry->financial_category_id;
            $this->amount = $entry->amount_cents->decimal();
            $this->dueDate = $entry->due_date->toDateString();
            $this->monthly = $entry->recurrence === 'monthly';
            $this->notes = (string) $entry->notes;
        } else {
            $this->kind = request()->query('tipo') === 'receivable' ? 'receivable' : 'payable';
            $this->dueDate = CarbonImmutable::now(Setting::read('timezone', 'America/Sao_Paulo'))->toDateString();
        }
    }

    public function save(FinanceBook $book): void
    {
        $this->authorize('finance.manage');
        $this->validate([
            'description' => ['required', 'string', 'max:160'],
            'counterparty' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', fn ($a, $v, $fail) => Money::isValid($v) && Money::parse($v)->cents > 0 ? null : $fail('Valor inválido.')],
            'dueDate' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['description' => 'descrição', 'amount' => 'valor', 'dueDate' => 'vencimento']);

        $data = [
            'kind' => $this->kind,
            'description' => $this->description,
            'counterparty' => $this->counterparty,
            'category_id' => $this->categoryId ?: null,
            'amount' => Money::parse($this->amount),
            'due_date' => $this->dueDate,
            'recurrence' => $this->monthly ? 'monthly' : 'none',
            'notes' => $this->notes ?: null,
        ];

        try {
            $this->entryId
                ? $book->update(FinancialEntry::findOrFail($this->entryId), $data, Auth::guard('web')->user())
                : $book->create($data, Auth::guard('web')->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key === 'category_id' ? 'categoryId' : $key, $messages[0]);
            }

            return;
        }

        session()->flash('status', 'Lançamento salvo.');
        $this->redirectRoute('finance.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.tenant.finance.entry-form', [
            'categories' => FinancialCategory::where('kind', $this->kind)->orderBy('name')->get(),
        ])->title($this->entryId ? 'Editar lançamento' : ($this->kind === 'payable' ? 'Nova conta a pagar' : 'Nova conta a receber'));
    }
}
