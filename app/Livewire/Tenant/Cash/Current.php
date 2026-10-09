<?php

namespace App\Livewire\Tenant\Cash;

use App\Modules\Access\Models\User;
use App\Modules\Cash\CashLedger;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\MovementType;
use App\Modules\Orders\Models\PaymentMethod;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Caixa do operador: abrir, sangria/suprimento e fechar com conferência. */
#[Title('Caixa')]
class Current extends Component
{
    public ?int $registerId = null;

    public string $opening = '0,00';

    public string $movementType = 'withdrawal';

    public string $movementAmount = '';

    public string $movementReason = '';

    public bool $closing = false;

    /** @var array<string, string> código da forma de pagamento => valor contado */
    public array $counted = [];

    public string $closingNotes = '';

    public function mount(): void
    {
        $this->registerId = CashRegister::where('is_active', true)
            ->whereDoesntHave('sessions', fn ($q) => $q->where('status', 'open'))
            ->value('id');
    }

    public function open(CashLedger $ledger): void
    {
        $this->authorize('cash.operate');
        $this->validateMoney('opening', $this->opening);

        $this->attempt(fn () => $ledger->open(CashRegister::where('is_active', true)->findOrFail($this->registerId), $this->user(), Money::parse($this->opening)));
    }

    public function addMovement(CashLedger $ledger): void
    {
        $this->authorize('cash.operate');
        $this->validateMoney('movementAmount', $this->movementAmount);
        $session = $ledger->currentFor($this->user()) ?? abort(409);

        $this->attempt(function () use ($ledger, $session) {
            $ledger->adjust($session, MovementType::from($this->movementType), Money::parse($this->movementAmount), $this->user(), $this->movementReason);
            $this->reset('movementAmount', 'movementReason');
        });
    }

    public function startClosing(): void
    {
        $this->closing = true;
        $this->counted = PaymentMethod::orderBy('position')->pluck('code')->mapWithKeys(fn ($code) => [$code => ''])->all();
    }

    public function close(CashLedger $ledger): void
    {
        $this->authorize('cash.operate');
        $session = $ledger->currentFor($this->user()) ?? abort(409);

        foreach ($this->counted as $code => $value) {
            $this->validateMoney("counted.{$code}", $value === '' ? '0' : $value);
        }

        $counted = array_map(fn ($v) => Money::parse($v === '' ? '0' : $v), $this->counted);

        $this->attempt(function () use ($ledger, $session, $counted) {
            $closed = $ledger->close($session, $this->user(), $counted, $this->closingNotes);
            $this->redirectRoute('cash.report', $closed, navigate: true);
        });
    }

    private function validateMoney(string $field, string $value): void
    {
        if (! Money::isValid($value) || Money::parse($value)->cents < 0) {
            throw ValidationException::withMessages([$field => 'Valor inválido.']);
        }
    }

    private function attempt(callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }
        }
    }

    private function user(): User
    {
        return Auth::guard('web')->user();
    }

    public function render()
    {
        $session = app(CashLedger::class)->currentFor($this->user());

        return view('livewire.tenant.cash.current', [
            'session' => $session?->load('register'),
            'registers' => CashRegister::where('is_active', true)->with(['sessions' => fn ($q) => $q->where('status', 'open')->with('operator')])->orderBy('name')->get(),
            'expected' => $session?->expectedByMethod() ?? [],
            'totals' => $session?->totalsByType() ?? [],
            'methods' => PaymentMethod::orderBy('position')->get()->keyBy('code'),
            'movements' => $session?->movements()->with(['method', 'user', 'order', 'command.diningTable'])->latest('id')->limit(50)->get() ?? collect(),
        ]);
    }
}
