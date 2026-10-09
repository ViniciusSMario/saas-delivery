<?php

namespace App\Platform\Actions;

use App\Tenancy\Enums\TenantStatus;
use App\Tenancy\Models\Tenant;
use Illuminate\Validation\ValidationException;

/** Suspensão e reativação. Nenhum dado do tenant é tocado. */
class ChangeTenantStatus
{
    public function suspend(Tenant $tenant, string $actor, ?string $reason = null): Tenant
    {
        $this->assertStatus($tenant, TenantStatus::Active, 'Só um estabelecimento ativo pode ser suspenso.');

        $tenant->update(['status' => TenantStatus::Suspended, 'suspended_at' => now()]);
        $tenant->recordEvent('suspended', array_filter(['reason' => $reason]), $actor);

        return $tenant;
    }

    public function reactivate(Tenant $tenant, string $actor): Tenant
    {
        $this->assertStatus($tenant, TenantStatus::Suspended, 'Só um estabelecimento suspenso pode ser reativado.');

        $tenant->update(['status' => TenantStatus::Active, 'suspended_at' => null]);
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
