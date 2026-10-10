<?php

namespace App\Tenancy\Contracts;

use App\Tenancy\Models\Tenant;

/**
 * O que a tela "Assinatura" do estabelecimento precisa da plataforma. A implementação
 * fica na plataforma (cobrança); as telas do tenant só conhecem este contrato e
 * recebem arrays simples.
 */
interface SubscriptionPortal
{
    /**
     * @return array{
     *     status: ?string, status_label: string, trial_days_left: int, trial_ends_at: ?string,
     *     period_ends_at: ?string, pay_url: ?string, current_plan: ?string,
     *     plans: list<array{slug: string, name: string, price: string, description: ?string, highlights: list<string>, current: bool}>,
     *     usage: list<array{label: string, used: int, limit: ?int}>,
     *     invoices: list<array{description: ?string, amount: string, due: string, status: string, badge: string, url: ?string, paid: bool}>
     * }
     */
    public function overview(Tenant $tenant): array;

    /** @return string|null link para pagar a fatura em aberto */
    public function subscribe(Tenant $tenant, string $planSlug, string $actor): ?string;

    public function cancel(Tenant $tenant, string $actor): void;
}
