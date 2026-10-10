<?php

namespace App\Livewire\Platform\Tenants;

use App\Platform\Actions\ChangeTenantStatus;
use App\Platform\Actions\TenantDeletion;
use App\Platform\Backups\TenantBackups;
use App\Platform\Billing\Subscriptions;
use App\Platform\Models\Invoice;
use App\Platform\Models\Plan;
use App\Platform\Models\TenantBackup;
use App\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.platform')]
class Show extends Component
{
    #[Locked]
    public string $tenantId;

    public string $reason = '';

    /** Para agendar a exclusão, digita-se o slug (evita clique por engano). */
    public string $confirmSlug = '';

    public ?int $planId = null;

    public function mount(Tenant $tenant): void
    {
        $this->tenantId = $tenant->id;
        $this->planId = $tenant->plan_id;
    }

    public function suspend(ChangeTenantStatus $action): void
    {
        $this->authorize('platform.manage-tenants');
        $this->validate(['reason' => ['nullable', 'string', 'max:200']]);

        $action->suspend($this->tenant(), $this->actor(), $this->reason ?: null);
        $this->done('Estabelecimento suspenso.');
    }

    public function reactivate(ChangeTenantStatus $action): void
    {
        $this->authorize('platform.manage-tenants');

        $action->reactivate($this->tenant(), $this->actor());
        $this->done('Estabelecimento reativado.');
    }

    public function backupNow(TenantBackups $backups): void
    {
        $this->authorize('platform.manage-tenants');

        try {
            $backups->create($this->tenant(), 'manual', $this->actor());
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['backup' => 'O backup falhou: '.$e->getMessage()]);
        }

        $this->done('Backup concluído.');
    }

    public function restore(int $backupId, TenantBackups $backups): void
    {
        $this->authorize('platform.manage-tenants');
        $backup = TenantBackup::where('tenant_id', $this->tenantId)->done()->findOrFail($backupId);

        try {
            $backups->restore($backup, $this->actor());
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['backup' => 'A restauração falhou e nada foi alterado: '.$e->getMessage()]);
        }

        $this->done('Restaurado para '.$backup->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i').'. O estado anterior ficou salvo como backup "antes da restauração".');
    }

    public function scheduleDeletion(TenantDeletion $deletion): void
    {
        $this->authorize('platform.manage-tenants');
        $this->resetErrorBag('confirmSlug');
        $tenant = $this->tenant();

        if ($this->confirmSlug !== $tenant->slug) {
            throw ValidationException::withMessages(['confirmSlug' => "Digite {$tenant->slug} para confirmar."]);
        }

        $deletion->schedule($tenant, $this->actor(), $this->reason ?: null);
        $this->done('Exclusão agendada para '.$tenant->deletion_scheduled_at->timezone('America/Sao_Paulo')->format('d/m/Y').'. O dono foi avisado por e-mail.');
    }

    public function cancelDeletion(TenantDeletion $deletion): void
    {
        $this->authorize('platform.manage-tenants');

        $deletion->cancel($this->tenant(), $this->actor());
        $this->done('Exclusão cancelada.');
    }

    public function setPlan(Subscriptions $subscriptions): void
    {
        $this->authorize('platform.manage-tenants');
        $plan = Plan::find($this->planId) ?? throw ValidationException::withMessages(['status' => 'Escolha um plano.']);

        $subscriptions->setPlan($this->tenant(), $plan, $this->actor());
        $this->done("Plano alterado para {$plan->name}.");
    }

    public function extendTrial(Subscriptions $subscriptions): void
    {
        $this->authorize('platform.manage-tenants');

        $subscriptions->extendTrial($this->tenant(), 7, $this->actor());
        $this->done('Teste grátis estendido por 7 dias.');
    }

    public function makeCourtesy(Subscriptions $subscriptions): void
    {
        $this->authorize('platform.manage-tenants');

        $subscriptions->makeCourtesy($this->tenant(), $this->actor());
        $this->done('Estabelecimento em cortesia: sem cobrança.');
    }

    private function done(string $message): void
    {
        session()->flash('status', $message);
        $this->redirectRoute('platform.tenants.show', $this->tenantId, navigate: true);
    }

    private function actor(): string
    {
        return Auth::guard('platform')->user()->email;
    }

    private function tenant(): Tenant
    {
        return Tenant::withTrashed()->findOrFail($this->tenantId);
    }

    public function render()
    {
        $tenant = $this->tenant()->load(['domains', 'plan']);

        return view('livewire.platform.tenants.show', [
            'tenant' => $tenant,
            'backups' => TenantBackup::where('tenant_id', $tenant->id)->latest('created_at')->latest('id')->limit(30)->get(),
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort')->orderBy('price_cents')->get(),
            'invoices' => Invoice::where('tenant_id', $tenant->id)->latest('due_date')->latest('id')->limit(6)->get(),
            'events' => $tenant->events()->latest('created_at')->latest('id')->limit(50)->get(),
        ])->title($tenant->name);
    }
}
