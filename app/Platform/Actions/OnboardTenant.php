<?php

namespace App\Platform\Actions;

use App\Modules\Establishment\Actions\InitializeEstablishment;
use App\Tenancy\Actions\ProvisionTenant;
use App\Tenancy\Models\Tenant;

/**
 * Cadastro completo de um estabelecimento: provisiona o schema e faz a
 * configuração inicial (dados do estabelecimento e usuário dono).
 * Usado pelo admin da plataforma e por tenants:create.
 */
class OnboardTenant
{
    public function __construct(
        private readonly ProvisionTenant $provision,
        private readonly InitializeEstablishment $initialize,
    ) {
    }

    public function handle(
        string $slug,
        string $name,
        ?string $document = null,
        ?string $ownerName = null,
        ?string $ownerEmail = null,
        ?string $domain = null,
        ?int $planId = null,
        ?string $actor = null,
    ): Tenant {
        $tenant = $this->provision->handle(
            $slug,
            $name,
            $domain,
            $document,
            $planId,
            fn () => $this->initialize->handle($name, $document, $ownerName, $ownerEmail),
        );

        if ($actor) {
            $tenant->recordEvent('onboarded', ['owner_email' => $ownerEmail], $actor);
        }

        return $tenant;
    }
}
