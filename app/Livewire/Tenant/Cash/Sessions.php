<?php

namespace App\Livewire\Tenant\Cash;

use App\Modules\Cash\CashLedger;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Access\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/** Gerência: todos os caixas, sessões e cadastro de caixas físicos. */
#[Title('Caixas')]
class Sessions extends Component
{
    use WithPagination;

    public string $newRegister = '';

    public function addRegister(): void
    {
        $this->authorize('cash.manage');
        $this->validate(['newRegister' => ['required', 'string', 'max:40', Rule::unique('tenant.cash_registers', 'name')]], attributes: ['newRegister' => 'nome']);

        $register = CashRegister::create(['name' => trim($this->newRegister)]);
        Audit::record('cash.register_created', $register, ['nome' => $register->name]);
        $this->reset('newRegister');
    }

    /** Gerente fecha o caixa de outro operador (sem conferência: tudo como esperado). */
    public function forceClose(int $sessionId, CashLedger $ledger): void
    {
        $this->authorize('cash.manage');
        $session = CashSession::open()->findOrFail($sessionId);
        $expected = $session->expectedByMethod();

        $ledger->close($session, Auth::guard('web')->user(), $expected, 'Fechado pela gerência sem conferência.');
    }

    public function render()
    {
        return view('livewire.tenant.cash.sessions', [
            'open' => CashSession::open()->with(['register', 'operator'])->get(),
            'sessions' => CashSession::where('status', 'closed')->with(['register', 'operator'])->latest('closed_at')->paginate(20),
            'registers' => CashRegister::orderBy('name')->get(),
            'zero' => Money::zero(),
        ]);
    }
}
