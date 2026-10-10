<?php

namespace App\Platform\Billing;

use App\Platform\Models\Plan;
use App\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Gateway de mentira para desenvolvimento e testes: nada sai da máquina. O link da
 * fatura abre uma página local com o botão "Simular pagamento".
 */
class FakeGateway implements BillingGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function createCustomer(Tenant $tenant, array $customer): string
    {
        return 'fake_cus_'.Str::lower(Str::random(12));
    }

    public function createSubscription(Tenant $tenant, Plan $plan, CarbonInterface $firstDueDate): array
    {
        $subscriptionId = 'fake_sub_'.Str::lower(Str::random(12));

        return [
            'subscription_id' => $subscriptionId,
            'charges' => [$this->charge($plan, CarbonImmutable::parse($firstDueDate), $subscriptionId)],
        ];
    }

    public function changePlan(string $subscriptionId, Plan $plan): void
    {
    }

    public function cancelSubscription(string $subscriptionId): void
    {
    }

    /** Formato próprio, usado pela página de teste: {id, status, charge_id, amount_cents, due_date}. */
    public function parseWebhook(Request $request): ?WebhookEvent
    {
        if (! $request->filled(['id', 'charge_id', 'status'])) {
            return null;
        }

        return new WebhookEvent(
            id: (string) $request->input('id'),
            type: WebhookEvent::CHARGE_UPDATED,
            charge: new Charge(
                id: (string) $request->input('charge_id'),
                amountCents: (int) $request->input('amount_cents', 0),
                dueDate: CarbonImmutable::parse($request->input('due_date', now()->toDateString())),
                status: (string) $request->input('status'),
            ),
            payload: $request->all(),
        );
    }

    /** Próxima cobrança mensal (o "gateway" de mentira não agenda nada sozinho). */
    public function charge(Plan $plan, CarbonImmutable $dueDate, ?string $subscriptionId = null): Charge
    {
        $id = 'fake_pay_'.Str::lower(Str::random(16));

        return new Charge(
            id: $id,
            amountCents: (int) $plan->price_cents,
            dueDate: $dueDate,
            status: 'pending',
            invoiceUrl: URL::signedRoute('billing.fake.show', ['charge' => $id]),
            subscriptionId: $subscriptionId,
            description: "Plano {$plan->name}",
        );
    }
}
