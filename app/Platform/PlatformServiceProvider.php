<?php

namespace App\Platform;

use App\Platform\Billing\AsaasGateway;
use App\Platform\Billing\BillingGateway;
use App\Platform\Billing\FakeGateway;
use App\Platform\Billing\Portal;
use App\Platform\Models\PlatformUser;
use App\Tenancy\Contracts\SubscriptionPortal;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Gateway de cobrança conforme config/platform.php (fake em desenvolvimento e testes).
        $this->app->singleton(BillingGateway::class, fn () => match (config('platform.billing.driver')) {
            'asaas' => new AsaasGateway,
            default => new FakeGateway,
        });

        $this->app->bind(SubscriptionPortal::class, Portal::class);

        // As ações Livewire do admin rodam no domínio do admin, sem tenant. A rota de
        // update do tenant não tem domínio e casa com qualquer host, então esta precisa
        // estar ANTES dela na coleção. O Livewire registra a rota padrão (que o tenant
        // reaproveita) no boot dele; "booting" roda antes do boot de todos os providers.
        // O domínio vai no grupo: a coleção indexa por domínio + URI no registro.
        $this->app->booting(function () {
            Route::domain(config('tenancy.platform_domain'))->middleware('platform')->group(function () {
                Route::post('/livewire/update', [HandleRequests::class, 'handleUpdate'])->name('platform.livewire.update');
            });
        });
    }

    public function boot(): void
    {
        Gate::define('platform.manage-tenants', fn (mixed $user) => $user instanceof PlatformUser && $user->isAdmin());
    }
}
