<?php

namespace App\Tenancy;

use App\Tenancy\Exceptions\MissingTenantForJob;
use App\Tenancy\Jobs\RunInTenant;
use App\Tenancy\Jobs\TenantAware;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\FilePreviewController;
use Livewire\Features\SupportFileUploads\FileUploadController;
use Livewire\Livewire;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SchemaManager::class);
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        $this->registerQueuePayloadHook();
        $this->routeLivewireThroughTenant();
    }

    /** Jobs TenantAware levam o id do tenant ativo no payload. */
    private function registerQueuePayloadHook(): void
    {
        Queue::createPayloadUsing(function (?string $connection, ?string $queue, array $payload): array {
            // Neste ponto o Laravel ainda passa o objeto do job (serializa depois).
            $command = $payload['data']['command'] ?? null;

            if (! is_object($command) || ! in_array(TenantAware::class, class_uses_recursive($command), true)) {
                return [];
            }

            $class = $command::class;

            $tenantId = app(TenantContext::class)->id()
                ?? throw MissingTenantForJob::onDispatch($class);

            return [RunInTenant::PAYLOAD_KEY => $tenantId];
        });
    }

    /** As ações e uploads do Livewire passam pelo mesmo middleware de tenant. */
    private function routeLivewireThroughTenant(): void
    {
        Livewire::setUpdateRoute(fn ($handle) => Route::post('/livewire/update', $handle)->middleware('tenant'));

        FileUploadController::$defaultMiddleware = ['tenant'];
        FilePreviewController::$middleware = ['tenant'];
        config(['livewire.temporary_file_upload.disk' => 'tenant']);
    }
}
