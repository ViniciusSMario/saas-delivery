<?php

namespace App\Platform\Billing;

use App\Platform\Models\Invoice;
use App\Platform\Models\Plan;
use App\Tenancy\Contracts\SubscriptionPortal;
use App\Tenancy\Models\Tenant;
use Illuminate\Validation\ValidationException;

/** Implementação do contrato usado pela tela "Assinatura" do estabelecimento. */
class Portal implements SubscriptionPortal
{
    public const STATUS_LABELS = [
        'trialing' => 'Teste grátis',
        'active' => 'Ativa',
        'past_due' => 'Pagamento pendente',
        'canceled' => 'Cancelada',
    ];

    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly TenantUsage $usage,
    ) {
    }

    public function overview(Tenant $tenant): array
    {
        $tenant->loadMissing('plan');
        $used = $this->usage->of($tenant);

        return [
            'status' => $tenant->subscription_status,
            'status_label' => self::STATUS_LABELS[$tenant->subscription_status] ?? 'Cortesia',
            'trial_days_left' => $tenant->trialDaysLeft(),
            'trial_ends_at' => $tenant->trial_ends_at?->timezone('America/Sao_Paulo')->format('d/m/Y'),
            'period_ends_at' => $tenant->current_period_ends_at?->timezone('America/Sao_Paulo')->format('d/m/Y'),
            'pay_url' => $tenant->openInvoiceUrl(),
            'current_plan' => $tenant->plan?->slug,
            'plans' => Plan::available()->get()->map(fn (Plan $plan) => [
                'slug' => $plan->slug,
                'name' => $plan->name,
                'price' => $plan->price()->format(),
                'description' => $plan->description,
                'highlights' => $plan->highlights(),
                'current' => $plan->id === $tenant->plan_id,
            ])->all(),
            'usage' => collect(Plan::LIMIT_LABELS)->map(fn (array $labels, string $key) => [
                'label' => ucfirst($labels[0]),
                'used' => $used[$key],
                'limit' => $tenant->planLimit($key),
            ])->values()->all(),
            'invoices' => $tenant->invoices()->latest('due_date')->latest('id')->limit(12)->get()->map(fn (Invoice $invoice) => [
                'description' => $invoice->description,
                'amount' => $invoice->amount()->format(),
                'due' => $invoice->due_date->format('d/m/Y'),
                'status' => $invoice->statusLabel(),
                'badge' => $invoice->statusBadge(),
                'url' => in_array($invoice->status, ['pending', 'overdue'], true) ? $invoice->invoice_url : null,
                'paid' => $invoice->status === 'paid',
            ])->all(),
        ];
    }

    public function subscribe(Tenant $tenant, string $planSlug, string $actor): ?string
    {
        $plan = Plan::available()->where('slug', $planSlug)->first();

        if (! $plan) {
            throw ValidationException::withMessages(['plan' => 'Plano não encontrado.']);
        }

        // Não deixa trocar para um plano menor do que o uso atual.
        $used = $this->usage->of($tenant);

        foreach (Plan::LIMIT_LABELS as $key => [$label]) {
            $limit = $plan->limit($key);

            if ($key !== 'orders_per_month' && $limit !== null && $used[$key] > $limit) {
                throw ValidationException::withMessages(['plan' => "O plano {$plan->name} permite até {$limit} {$label} e você usa {$used[$key]}. Desative ou remova o excedente antes de trocar."]);
            }
        }

        $tenant->billing_email ??= $actor;

        return $this->subscriptions->subscribe($tenant, $plan, $actor);
    }

    public function cancel(Tenant $tenant, string $actor): void
    {
        $this->subscriptions->cancel($tenant, $actor);
    }
}
