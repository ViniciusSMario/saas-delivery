<?php

namespace App\Platform\Actions;

use App\Tenancy\Enums\TenantStatus;
use App\Tenancy\Models\Tenant;
use Illuminate\Validation\ValidationException;

/** Suspensão e reativação. Nenhum dado do tenant é tocado. */
class ChangeTenantStatus
{
    /** @param  bool  $billing  suspensão por falta de pagamento (reativa sozinha quando a fatura é paga) */
    public function suspend(Tenant $tenant, string $actor, ?string $reason = null, bool $billing = false): Tenant
    {
        $this->assertStatus($tenant, TenantStatus::Active, 'Só um estabelecimento ativo pode ser suspenso.');

        $tenant->update(['status' => TenantStatus::Suspended, 'suspended_at' => now(), 'suspension_reason' => $billing ? 'billing' : null]);
        $tenant->recordEvent('suspended', array_filter(['reason' => $reason, 'billing' => $billing ?: null]), $actor);

        return $tenant;
    }

    public function reactivate(Tenant $tenant, string $actor): Tenant
    {
        $this->assertStatus($tenant, TenantStatus::Suspended, 'Só um estabelecimento suspenso pode ser reativado.');

        $tenant->update(['status' => TenantStatus::Active, 'suspended_at' => null, 'suspension_reason' => null]);
        $tenant->recordEvent('reactivated', [], $actor);

        return $tenant;
    }

    private function assertStatus(Tenant $tenant, TenantStatus $expected, string $message): void
    {
        if ($tenant->status !== $expected) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }
}
