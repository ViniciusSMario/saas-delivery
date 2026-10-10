<?php

namespace App\Platform\Billing;

use App\Platform\Actions\ChangeTenantStatus;
use App\Platform\Models\Invoice;
use App\Platform\Models\Plan;
use App\Platform\Notifications\TenantNotice;
use App\Platform\TenantOwners;
use App\Tenancy\Enums\TenantStatus;
use App\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo da assinatura de um estabelecimento:
 *   teste grátis (trialing) → ativa (active, fatura paga) → inadimplente (past_due,
 *   fatura vencida) → suspenso depois da carência. Pagou, reativa sozinho.
 *   Cancelada (canceled): acesso até o fim do período pago.
 * subscription_status null = cortesia/legado: sem cobrança e sem bloqueio.
 */
class Subscriptions
{
    public function __construct(
        private readonly BillingGateway $gateway,
        private readonly ChangeTenantStatus $status,
        private readonly TenantOwners $owners,
    ) {
    }

    public function startTrial(Tenant $tenant, Plan $plan, ?string $billingEmail = null): Tenant
    {
        $tenant->update([
            'plan_id' => $plan->id,
            'subscription_status' => 'trialing',
            'trial_ends_at' => now()->addDays($plan->trial_days),
            'billing_email' => $billingEmail ?? $tenant->billing_email,
        ]);
        $tenant->recordEvent('trial_started', ['plan' => $plan->slug, 'until' => $tenant->trial_ends_at->toDateString()]);

        return $tenant;
    }

    /**
     * Assina (ou troca de plano). Durante o teste, a primeira cobrança vence no fim dele.
     *
     * @return string|null link para pagar a fatura em aberto
     */
    public function subscribe(Tenant $tenant, Plan $plan, string $actor): ?string
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages(['plan' => 'Este plano não está disponível.']);
        }

        if ($tenant->billing_subscription_id && $tenant->subscription_status !== 'canceled') {
            return $this->changePlan($tenant, $plan, $actor);
        }

        if (! $tenant->billing_email) {
            throw ValidationException::withMessages(['plan' => 'Informe o e-mail de cobrança.']);
        }

        $tenant->billing_customer_id ??= $this->gateway->createCustomer($tenant, [
            'name' => $tenant->name,
            'email' => $tenant->billing_email,
            'document' => $tenant->document,
        ]);
        $tenant->save();

        $onTrial = $tenant->onTrial();
        $firstDue = $onTrial ? CarbonImmutable::parse($tenant->trial_ends_at)->startOfDay() : CarbonImmutable::today();
        $result = $this->gateway->createSubscription($tenant, $plan, $firstDue);

        $tenant->update([
            'plan_id' => $plan->id,
            'billing_subscription_id' => $result['subscription_id'],
            // Fora do teste, a primeira fatura já é devida: fica "inadimplente" até pagar.
            'subscription_status' => $onTrial ? 'trialing' : 'past_due',
            'past_due_since' => $onTrial ? null : ($tenant->past_due_since ?? now()),
        ]);

        foreach ($result['charges'] as $charge) {
            $this->syncCharge($tenant, $charge);
        }

        $tenant->recordEvent('subscribed', ['plan' => $plan->slug, 'gateway' => $this->gateway->name()], $actor);

        return $tenant->openInvoiceUrl();
    }

    public function cancel(Tenant $tenant, string $actor): void
    {
        if ($tenant->billing_subscription_id) {
            $this->gateway->cancelSubscription($tenant->billing_subscription_id);
        }

        $tenant->invoices()->where('status', 'pending')->update(['status' => 'canceled']);
        $tenant->update(['subscription_status' => 'canceled', 'billing_subscription_id' => null]);
        $tenant->recordEvent('subscription_canceled', [], $actor);
    }

    /** Ajustes do admin da plataforma. */
    public function extendTrial(Tenant $tenant, int $days, string $actor): void
    {
        $base = $tenant->trial_ends_at?->isFuture() ? $tenant->trial_ends_at : now();

        $tenant->update(['subscription_status' => 'trialing', 'trial_ends_at' => $base->copy()->addDays($days), 'past_due_since' => null]);
        $tenant->recordEvent('trial_extended', ['days' => $days, 'until' => $tenant->trial_ends_at->toDateString()], $actor);
        $this->reactivateIfBillingSuspended($tenant, $actor);
    }

    /** Admin troca o plano sem passar pelo fluxo de assinatura (ajuste/negociação). */
    public function setPlan(Tenant $tenant, Plan $plan, string $actor): void
    {
        if ($tenant->billing_subscription_id && $tenant->subscription_status !== 'canceled') {
            $this->changePlan($tenant, $plan, $actor);

            return;
        }

        $from = $tenant->plan?->slug;
        $tenant->update(['plan_id' => $plan->id]);
        $tenant->recordEvent('plan_changed', ['from' => $from, 'to' => $plan->slug], $actor);
    }

    /** Cortesia: tira da cobrança (sem bloqueios por pagamento). */
    public function makeCourtesy(Tenant $tenant, string $actor): void
    {
        if ($tenant->billing_subscription_id) {
            $this->gateway->cancelSubscription($tenant->billing_subscription_id);
        }

        $tenant->invoices()->open()->update(['status' => 'canceled']);
        $tenant->update(['subscription_status' => null, 'billing_subscription_id' => null, 'past_due_since' => null, 'trial_ends_at' => null]);
        $tenant->recordEvent('courtesy_granted', [], $actor);
        $this->reactivateIfBillingSuspended($tenant, $actor);
    }

    /** Webhook do gateway. Idempotente: o mesmo evento não é processado duas vezes. */
    public function handle(WebhookEvent $event): bool
    {
        try {
            $logId = DB::connection('central')->table('billing_events')->insertGetId([
                'gateway' => $this->gateway->name(),
                'event_id' => $event->id,
                'type' => $event->type,
                'payload' => json_encode($event->payload),
                'created_at' => now(),
            ]);
        } catch (QueryException) {
            return false; // já recebido
        }

        if ($event->type === WebhookEvent::CHARGE_UPDATED && $event->charge) {
            $tenant = Invoice::where('gateway_id', $event->charge->id)->first()?->tenant
                ?? ($event->charge->subscriptionId ? Tenant::where('billing_subscription_id', $event->charge->subscriptionId)->first() : null);

            if ($tenant) {
                $this->syncCharge($tenant, $event->charge);
            }
        }

        if ($event->type === WebhookEvent::SUBSCRIPTION_CANCELED && $event->subscriptionId) {
            Tenant::where('billing_subscription_id', $event->subscriptionId)->first()
                ?->update(['subscription_status' => 'canceled', 'billing_subscription_id' => null]);
        }

        DB::connection('central')->table('billing_events')->where('id', $logId)->update(['processed_at' => now()]);

        return true;
    }

    /** Cria/atualiza a fatura local e aplica o efeito no estabelecimento (pagou, venceu). */
    public function syncCharge(Tenant $tenant, Charge $charge): Invoice
    {
        $invoice = Invoice::firstOrNew(['gateway_id' => $charge->id]);
        $previous = $invoice->exists ? $invoice->status : null;

        $invoice->fill([
            'tenant_id' => $tenant->id,
            'plan_id' => $invoice->plan_id ?? $tenant->plan_id,
            'gateway' => $this->gateway->name(),
            'description' => $charge->description ?? $invoice->description ?? 'Assinatura '.config('app.name'),
            'amount_cents' => $charge->amountCents ?: (int) $invoice->amount_cents,
            'due_date' => $charge->dueDate->toDateString(),
            'status' => $charge->status,
            'invoice_url' => $charge->invoiceUrl ?? $invoice->invoice_url,
        ]);

        if ($charge->status === 'paid' && $previous !== 'paid') {
            $invoice->paid_at = now();
        }

        $invoice->save();

        if ($charge->status === 'paid' && $previous !== 'paid') {
            $this->paid($tenant->refresh(), $invoice);
        } elseif ($charge->status === 'overdue' && $previous !== 'overdue') {
            $this->overdue($tenant->refresh(), $invoice);
        }

        return $invoice;
    }

    /**
     * Rotina diária (billing:check): aviso de fim de teste, teste vencido, suspensão
     * depois da carência e fim do período de quem cancelou.
     *
     * @return array<string, int>
     */
    public function check(?Carbon $now = null): array
    {
        $now ??= now();
        $count = ['reminders' => 0, 'trials_ended' => 0, 'suspended' => 0, 'charges' => 0];

        // 1. Teste acabando em poucos dias, sem assinatura: lembrete (uma vez).
        $soon = Tenant::where('subscription_status', 'trialing')->whereNull('billing_subscription_id')
            ->whereBetween('trial_ends_at', [$now, $now->copy()->addDays(config('platform.billing.trial_reminder_days'))])->get();

        foreach ($soon as $tenant) {
            if (! $tenant->events()->where('type', 'trial_reminder_sent')->exists()) {
                $this->notify($tenant, 'Seu teste grátis está acabando', [
                    "O teste grátis de {$tenant->name} termina em {$tenant->trialDaysLeft()} dia(s).",
                    'Escolha um plano para continuar usando sem interrupção. Leva um minuto.',
                ], 'Escolher plano', $this->tenantUrl($tenant, '/assinatura'));
                $tenant->recordEvent('trial_reminder_sent');
                $count['reminders']++;
            }
        }

        // 2. Teste vencido: passa a dever (a primeira fatura, se assinou, venceu agora).
        foreach (Tenant::where('subscription_status', 'trialing')->where('trial_ends_at', '<=', $now)->get() as $tenant) {
            $tenant->update(['subscription_status' => 'past_due', 'past_due_since' => $tenant->trial_ends_at]);
            $tenant->recordEvent('trial_ended');
            $this->notify($tenant, 'Seu teste grátis terminou', [
                "O teste grátis de {$tenant->name} terminou.",
                'Para não perder o acesso, '.($tenant->billing_subscription_id ? 'pague a primeira fatura' : 'escolha um plano').' em até '.config('platform.billing.grace_days').' dias.',
            ], $tenant->billing_subscription_id ? 'Pagar fatura' : 'Escolher plano', $tenant->openInvoiceUrl() ?? $this->tenantUrl($tenant, '/assinatura'));
            $count['trials_ended']++;
        }

        // 3. Inadimplente além da carência, ou cancelado depois do fim do período: suspende.
        $grace = $now->copy()->subDays(config('platform.billing.grace_days'));
        $toSuspend = Tenant::where('status', TenantStatus::Active)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('subscription_status', 'past_due')->where('past_due_since', '<=', $grace))
                ->orWhere(fn ($q) => $q->where('subscription_status', 'canceled')
                    ->whereRaw('coalesce(current_period_ends_at, trial_ends_at, created_at) <= ?', [$now])))
            ->get();

        foreach ($toSuspend as $tenant) {
            $this->status->suspend($tenant, 'billing', 'Falta de pagamento', billing: true);
            $this->notify($tenant, 'Acesso suspenso por falta de pagamento', [
                "O acesso a {$tenant->name} foi suspenso porque a assinatura não está paga.",
                'Assim que o pagamento for confirmado, tudo volta a funcionar sozinho. Nenhum dado foi apagado.',
            ], $tenant->openInvoiceUrl() ? 'Pagar fatura' : null, $tenant->openInvoiceUrl());
            $count['suspended']++;
        }

        // 4. Gateway de teste não gera a cobrança do mês seguinte sozinho: geramos aqui.
        if ($this->gateway instanceof FakeGateway) {
            $due = Tenant::where('subscription_status', 'active')->whereNotNull('billing_subscription_id')
                ->where('current_period_ends_at', '<=', $now->copy()->addDays(10))->with('plan')->get();

            foreach ($due as $tenant) {
                if ($tenant->plan && ! $tenant->invoices()->open()->exists()) {
                    $this->syncCharge($tenant, $this->gateway->charge($tenant->plan, CarbonImmutable::parse($tenant->current_period_ends_at)->startOfDay(), $tenant->billing_subscription_id));
                    $count['charges']++;
                }
            }
        }

        return $count;
    }

    public function gateway(): BillingGateway
    {
        return $this->gateway;
    }

    private function changePlan(Tenant $tenant, Plan $plan, string $actor): ?string
    {
        if ($tenant->plan_id !== $plan->id) {
            $this->gateway->changePlan($tenant->billing_subscription_id, $plan);
            $tenant->invoices()->where('status', 'pending')->update(['amount_cents' => $plan->price_cents, 'plan_id' => $plan->id]);
            $from = $tenant->plan?->slug;
            $tenant->update(['plan_id' => $plan->id]);
            $tenant->recordEvent('plan_changed', ['from' => $from, 'to' => $plan->slug], $actor);
        }

        return $tenant->openInvoiceUrl();
    }

    private function paid(Tenant $tenant, Invoice $invoice): void
    {
        // O período pago começa no vencimento (ou no fim do período atual, se for depois).
        $start = CarbonImmutable::parse($invoice->due_date);

        if ($tenant->current_period_ends_at && $tenant->current_period_ends_at->gt($start)) {
            $start = CarbonImmutable::parse($tenant->current_period_ends_at);
        }

        $tenant->update([
            'subscription_status' => $tenant->subscription_status === 'canceled' ? 'canceled' : 'active',
            'current_period_ends_at' => $start->addMonthNoOverflow(),
            'past_due_since' => null,
        ]);
        $tenant->recordEvent('invoice_paid', ['invoice' => $invoice->id, 'amount' => $invoice->amount()->format()], 'billing');

        $this->reactivateIfBillingSuspended($tenant, 'billing');

        $this->notify($tenant, 'Pagamento confirmado', [
            "Recebemos o pagamento de {$invoice->amount()->format()} da assinatura de {$tenant->name}. Obrigado!",
            'Próximo vencimento: '.$tenant->current_period_ends_at->format('d/m/Y').'.',
        ]);
    }

    private function overdue(Tenant $tenant, Invoice $invoice): void
    {
        if (in_array($tenant->subscription_status, ['active', 'trialing'], true)) {
            $tenant->update(['subscription_status' => 'past_due', 'past_due_since' => $tenant->past_due_since ?? $invoice->due_date]);
        }

        $tenant->recordEvent('invoice_overdue', ['invoice' => $invoice->id], 'billing');
        $this->notify($tenant, 'Fatura vencida', [
            "A fatura de {$invoice->amount()->format()} da assinatura de {$tenant->name} venceu em {$invoice->due_date->format('d/m/Y')}.",
            'Para evitar a suspensão do acesso, pague em até '.config('platform.billing.grace_days').' dias.',
        ], $invoice->invoice_url ? 'Pagar fatura' : null, $invoice->invoice_url);
    }

    private function reactivateIfBillingSuspended(Tenant $tenant, string $actor): void
    {
        if ($tenant->status === TenantStatus::Suspended && $tenant->suspension_reason === 'billing') {
            $this->status->reactivate($tenant, $actor);
        }
    }

    private function notify(Tenant $tenant, string $subject, array $lines, ?string $action = null, ?string $url = null): void
    {
        $this->owners->notify($tenant, new TenantNotice($subject, $lines, $action, $url));
    }

    private function tenantUrl(Tenant $tenant, string $path): string
    {
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);

        return 'http://'.$tenant->primaryDomain().($port ? ':'.$port : '').$path;
    }
}
