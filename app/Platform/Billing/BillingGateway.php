<?php

namespace App\Platform\Billing;

use App\Platform\Models\Plan;
use App\Tenancy\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/**
 * Gateway de cobrança. Implementações: FakeGateway (desenvolvimento e testes) e
 * AsaasGateway (Pix, boleto e cartão). Trocar de gateway = nova classe + config.
 */
interface BillingGateway
{
    public function name(): string;

    /** @param  array{name: string, email: string, document?: ?string, phone?: ?string}  $customer */
    public function createCustomer(Tenant $tenant, array $customer): string;

    /**
     * Assinatura mensal; a primeira cobrança vence em $firstDueDate.
     *
     * @return array{subscription_id: string, charges: list<Charge>}
     */
    public function createSubscription(Tenant $tenant, Plan $plan, CarbonInterface $firstDueDate): array;

    /** Troca o valor da assinatura (e das cobranças ainda não pagas). */
    public function changePlan(string $subscriptionId, Plan $plan): void;

    public function cancelSubscription(string $subscriptionId): void;

    /** Valida a origem e traduz o webhook; null = evento que não interessa. */
    public function parseWebhook(Request $request): ?WebhookEvent;
}
