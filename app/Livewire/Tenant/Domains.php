<?php

namespace App\Livewire\Tenant;

use App\Modules\Access\Audit;
use App\Tenancy\CustomDomains;
use App\Tenancy\Models\TenantDomain;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Domínio próprio: cadastro, instruções de DNS, verificação e endereço principal. */
#[Title('Domínio próprio')]
class Domains extends Component
{
    public string $domain = '';

    /** @var array<int, array{verified: bool, txt_found: bool, cname: string|null}> */
    public array $checks = [];

    public function add(CustomDomains $domains): void
    {
        $this->authorize('establishment.manage');
        abort_unless(tenant()->hasFeature('custom_domain'), 403);
        $this->resetErrorBag();

        $record = $domains->add(tenant(), $this->domain);
        Audit::record('domain.added', null, ['dominio' => $record->domain]);
        $this->reset('domain');
    }

    public function verify(int $id, CustomDomains $domains): void
    {
        $this->authorize('establishment.manage');

        $this->checks[$id] = $domains->verify($this->find($id));
    }

    public function makePrimary(int $id, CustomDomains $domains): void
    {
        $this->authorize('establishment.manage');

        $domains->makePrimary($this->find($id));
        Audit::record('domain.primary', null, ['dominio' => $this->find($id)->domain]);
    }

    public function remove(int $id, CustomDomains $domains): void
    {
        $this->authorize('establishment.manage');
        $record = $this->find($id);

        $domains->remove($record);
        Audit::record('domain.removed', null, ['dominio' => $record->domain]);
    }

    private function find(int $id): TenantDomain
    {
        return TenantDomain::where('tenant_id', tenant()->id)->findOrFail($id);
    }

    public function render()
    {
        $tenant = tenant();

        return view('livewire.tenant.domains', [
            'domains' => $tenant->domains()->orderByDesc('is_primary')->orderBy('id')->get(),
            'subdomain' => $tenant->domains()->where('type', 'subdomain')->value('domain'),
            'allowed' => $tenant->hasFeature('custom_domain'),
        ]);
    }
}
