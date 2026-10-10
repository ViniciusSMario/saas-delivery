<?php

namespace App\Platform\Billing;

use App\Platform\Models\Plan;
use App\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Asaas (https://docs.asaas.com): Pix, boleto e cartão. A cobrança usa billingType
 * UNDEFINED: o cliente escolhe a forma de pagamento na fatura do Asaas.
 * Sandbox: ASAAS_URL=https://api-sandbox.asaas.com/v3 (padrão).
 */
class AsaasGateway implements BillingGateway
{
    private const PAID = ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'];

    private const CANCELED = ['REFUNDED', 'DELETED', 'REFUND_REQUESTED', 'CHARGEBACK_REQUESTED'];

    public function name(): string
    {
        return 'asaas';
    }

    public function createCustomer(Tenant $tenant, array $customer): string
    {
        return $this->post('/customers', array_filter([
            'name' => $customer['name'],
            'email' => $customer['email'],
            'cpfCnpj' => preg_replace('/\D/', '', (string) ($customer['document'] ?? '')) ?: null,
            'mobilePhone' => preg_replace('/\D/', '', (string) ($customer['phone'] ?? '')) ?: null,
            'externalReference' => $tenant->id,
            'notificationDisabled' => false,
        ]))['id'];
    }

    public function createSubscription(Tenant $tenant, Plan $plan, CarbonInterface $firstDueDate): array
    {
        $subscription = $this->post('/subscriptions', [
            'customer' => $tenant->billing_customer_id,
            'billingType' => 'UNDEFINED',
            'value' => $plan->price_cents / 100,
            'nextDueDate' => $firstDueDate->format('Y-m-d'),
            'cycle' => 'MONTHLY',
            'description' => "{$tenant->name} · plano {$plan->name}",
            'externalReference' => $tenant->id,
        ]);

        $payments = $this->client()->get("/subscriptions/{$subscription['id']}/payments")->throw()->json('data', []);

        return [
            'subscription_id' => $subscription['id'],
            'charges' => array_map(fn (array $payment) => $this->toCharge($payment), $payments),
        ];
    }

    public function changePlan(string $subscriptionId, Plan $plan): void
    {
        $this->post("/subscriptions/{$subscriptionId}", [
            'value' => $plan->price_cents / 100,
            'updatePendingPayments' => true,
        ]);
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        $this->client()->delete("/subscriptions/{$subscriptionId}")->throw();
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $token = (string) config('services.asaas.webhook_token');

        if ($token === '' || ! hash_equals($token, (string) $request->header('asaas-access-token'))) {
            throw new RuntimeException('Webhook do Asaas com token inválido.');
        }

        $event = (string) $request->input('event');
        $id = (string) ($request->input('id') ?: $event.':'.$request->input('payment.id').':'.$request->input('payment.status'));

        if (str_starts_with($event, 'PAYMENT_') && is_array($request->input('payment'))) {
            return new WebhookEvent($id, WebhookEvent::CHARGE_UPDATED, $this->toCharge($request->input('payment')), payload: $request->all());
        }

        if (in_array($event, ['SUBSCRIPTION_DELETED', 'SUBSCRIPTION_INACTIVATED'], true)) {
            return new WebhookEvent($id, WebhookEvent::SUBSCRIPTION_CANCELED, subscriptionId: $request->input('subscription.id'), payload: $request->all());
        }

        return null;
    }

    private function toCharge(array $payment): Charge
    {
        $status = (string) ($payment['status'] ?? 'PENDING');

        return new Charge(
            id: (string) $payment['id'],
            amountCents: (int) round(((float) ($payment['value'] ?? 0)) * 100),
            dueDate: CarbonImmutable::parse($payment['dueDate'] ?? now()),
            status: match (true) {
                ($payment['deleted'] ?? false) === true, in_array($status, self::CANCELED, true) => 'canceled',
                in_array($status, self::PAID, true) => 'paid',
                $status === 'OVERDUE' => 'overdue',
                default => 'pending',
            },
            invoiceUrl: $payment['invoiceUrl'] ?? null,
            subscriptionId: $payment['subscription'] ?? null,
            description: $payment['description'] ?? null,
        );
    }

    private function post(string $path, array $data): array
    {
        return $this->client()->post($path, $data)->throw()->json();
    }

    private function client(): PendingRequest
    {
        $key = (string) config('services.asaas.key');

        if ($key === '') {
            throw new RuntimeException('Configure ASAAS_API_KEY para usar o Asaas.');
        }

        return Http::baseUrl(rtrim((string) config('services.asaas.url'), '/'))
            ->withHeaders(['access_token' => $key, 'User-Agent' => config('app.name')])
            ->acceptJson()
            ->timeout(20);
    }
}
