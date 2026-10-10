<?php

namespace App\Platform\Billing;

/** Evento de cobrança já traduzido do formato do gateway. */
final readonly class WebhookEvent
{
    public const CHARGE_UPDATED = 'charge.updated';

    public const SUBSCRIPTION_CANCELED = 'subscription.canceled';

    public function __construct(
        public string $id,
        public string $type,
        public ?Charge $charge = null,
        public ?string $subscriptionId = null,
        public array $payload = [],
    ) {
    }
}
