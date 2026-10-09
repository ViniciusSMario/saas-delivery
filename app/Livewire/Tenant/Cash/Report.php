<?php

namespace App\Livewire\Tenant\Cash;

use App\Modules\Cash\Models\CashSession;
use App\Modules\Orders\Models\PaymentMethod;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Relatório de uma sessão de caixa (imprimível). O operador vê o próprio; a gerência, todos. */
class Report extends Component
{
    #[Locked]
    public int $sessionId;

    public function mount(CashSession $session): void
    {
        $user = Auth::guard('web')->user();
        abort_unless($session->opened_by === $user->id || $user->can('cash.manage'), 403);

        $this->sessionId = $session->id;
    }

    public function render()
    {
        $session = CashSession::with(['register', 'operator', 'closer'])->findOrFail($this->sessionId);
        $methods = PaymentMethod::orderBy('position')->get();
        $expected = $session->isOpen()
            ? $session->expectedByMethod()
            : collect($session->expected ?? [])->map(fn ($c) => Money::cents((int) $c))->all();

        return view('livewire.tenant.cash.report', [
            'session' => $session,
            'methods' => $methods,
            'expected' => $expected,
            'counted' => collect($session->counted ?? [])->map(fn ($c) => Money::cents((int) $c))->all(),
            'totals' => $session->totalsByType(),
            'movements' => $session->movements()->with(['method', 'user', 'order', 'command.diningTable'])->get(),
        ])->title('Caixa · '.$session->register->name);
    }
}
