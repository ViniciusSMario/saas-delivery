<?php

namespace App\Livewire\Tenant;

use App\Tenancy\Contracts\SubscriptionPortal;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Plano, uso, faturas e troca de plano. Só o dono (billing.manage). */
#[Title('Assinatura')]
class Subscription extends Component
{
    public function subscribe(string $plan): void
    {
        $this->authorize('billing.manage');

        $payUrl = app(SubscriptionPortal::class)->subscribe(tenant(), $plan, Auth::user()->email);

        session()->flash('status', $payUrl ? 'Plano escolhido. Pague a fatura para confirmar.' : 'Plano atualizado.');
        $this->redirectRoute('subscription', navigate: true);
    }

    public function cancel(): void
    {
        $this->authorize('billing.manage');

        app(SubscriptionPortal::class)->cancel(tenant(), Auth::user()->email);

        session()->flash('status', 'Assinatura cancelada. O acesso continua até o fim do período pago.');
        $this->redirectRoute('subscription', navigate: true);
    }

    public function render()
    {
        return view('livewire.tenant.subscription', [
            'data' => app(SubscriptionPortal::class)->overview(tenant()),
        ]);
    }
}
