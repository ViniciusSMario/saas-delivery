<?php

namespace App\Livewire\Platform\Tenants;

use App\Platform\Actions\ChangeTenantStatus;
use App\Tenancy\Enums\TenantStatus;
use App\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.platform')]
#[Title('Estabelecimentos')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function suspend(string $tenantId, ChangeTenantStatus $action): void
    {
        $this->authorize('platform.manage-tenants');

        $tenant = $action->suspend(Tenant::findOrFail($tenantId), Auth::guard('platform')->user()->email);
        session()->flash('status', "{$tenant->name} foi suspenso.");
        $this->redirectRoute('platform.tenants.index', navigate: true);
    }

    public function reactivate(string $tenantId, ChangeTenantStatus $action): void
    {
        $this->authorize('platform.manage-tenants');

        $tenant = $action->reactivate(Tenant::findOrFail($tenantId), Auth::guard('platform')->user()->email);
        session()->flash('status', "{$tenant->name} foi reativado.");
        $this->redirectRoute('platform.tenants.index', navigate: true);
    }

    public function render()
    {
        $tenants = Tenant::query()
            ->with(['domains' => fn ($q) => $q->orderByDesc('is_primary')])
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(fn ($q) => $q->whereRaw('lower(name) like ?', [$term])
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('document', 'like', $term));
            })
            ->latest()
            ->paginate(25);

        $counts = Tenant::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('livewire.platform.tenants.index', [
            'tenants' => $tenants,
            'counts' => $counts,
            'statuses' => TenantStatus::cases(),
        ]);
    }
}
