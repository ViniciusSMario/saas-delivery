<?php

namespace App\Platform\Billing;

use Carbon\CarbonImmutable;

/** Uma cobrança do gateway (vira/atualiza uma Invoice). */
final readonly class Charge
{
    public function __construct(
        public string $id,
        public int $amountCents,
        public CarbonImmutable $dueDate,
        public string $status, // pending | paid | overdue | canceled
        public ?string $invoiceUrl = null,
        public ?string $subscriptionId = null,
        public ?string $description = null,
    ) {
    }
}
