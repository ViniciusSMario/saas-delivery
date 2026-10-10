<?php

namespace App\Livewire\Platform\Billing;

use App\Platform\Models\Invoice;
use App\Platform\Models\Plan;
use App\Support\Money;
use App\Tenancy\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Visão geral da cobrança: receita recorrente, situação das assinaturas, faturas e planos. */
#[Layout('components.layouts.platform')]
#[Title('Assinaturas')]
class Index extends Component
{
    public string $filter = '';

    public function render()
    {
        $counts = Tenant::query()->selectRaw("coalesce(subscription_status, 'courtesy') as s, count(*) as n")->groupBy('s')->pluck('n', 's');

        $mrr = Tenant::query()->where('subscription_status', 'active')
            ->join('plans', 'plans.id', '=', 'tenants.plan_id')
            ->sum('plans.price_cents');

        $tenants = Tenant::query()->with('plan')
            ->when($this->filter !== '', fn ($q) => $this->filter === 'courtesy' ? $q->whereNull('subscription_status') : $q->where('subscription_status', $this->filter))
            ->orderByRaw("case subscription_status when 'past_due' then 0 when 'trialing' then 1 when 'active' then 2 else 3 end")
            ->orderBy('trial_ends_at')
            ->limit(100)->get();

        return view('livewire.platform.billing.index', [
            'counts' => $counts,
            'mrr' => Money::cents((int) $mrr),
            'openTotal' => Money::cents((int) Invoice::open()->sum('amount_cents')),
            'paidMonth' => Money::cents((int) Invoice::where('status', 'paid')->where('paid_at', '>=', now()->startOfMonth())->sum('amount_cents')),
            'tenants' => $tenants,
            'invoices' => Invoice::with('tenant')->latest('updated_at')->limit(15)->get(),
            'plans' => Plan::query()->withCount(['tenants' => fn ($q) => $q->whereNotNull('subscription_status')])->orderBy('sort')->orderBy('price_cents')->get(),
        ]);
    }
}
