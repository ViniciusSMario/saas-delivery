<?php

namespace App\Livewire\Platform\Tenants;

use App\Platform\Actions\ChangeTenantStatus;
use App\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.platform')]
class Show extends Component
{
    #[Locked]
    public string $tenantId;

    public string $reason = '';

    public function mount(Tenant $tenant): void
    {
        $this->tenantId = $tenant->id;
    }

    public function suspend(ChangeTenantStatus $action): void
    {
        $this->authorize('platform.manage-tenants');
        $this->validate(['reason' => ['nullable', 'string', 'max:200']]);

        $action->suspend($this->tenant(), Auth::guard('platform')->user()->email, $this->reason ?: null);
        session()->flash('status', 'Estabelecimento suspenso.');
        $this->redirectRoute('platform.tenants.show', $this->tenantId, navigate: true);
    }

    public function reactivate(ChangeTenantStatus $action): void
    {
        $this->authorize('platform.manage-tenants');

        $action->reactivate($this->tenant(), Auth::guard('platform')->user()->email);
        session()->flash('status', 'Estabelecimento reativado.');
        $this->redirectRoute('platform.tenants.show', $this->tenantId, navigate: true);
    }

    private function tenant(): Tenant
    {
        return Tenant::findOrFail($this->tenantId);
    }

    public function render()
    {
        $tenant = $this->tenant()->load(['domains', 'plan']);

        return view('livewire.platform.tenants.show', [
            'tenant' => $tenant,
            'events' => $tenant->events()->latest('created_at')->latest('id')->limit(50)->get(),
        ])->title($tenant->name);
    }
}
