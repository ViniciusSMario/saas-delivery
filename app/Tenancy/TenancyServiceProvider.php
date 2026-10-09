<?php

namespace App\Tenancy;

use App\Tenancy\Exceptions\MissingTenantForJob;
use App\Tenancy\Jobs\RunInTenant;
use App\Tenancy\Jobs\TenantAware;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\FileUploadController;
use Livewire\Livewire;

class TenancyServiceProvider extends ServiceProvider
{
    private static bool $payloadHookRegistered = false;

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
        // O hook é estático na fila; registrar uma vez por processo basta.
        if (self::$payloadHookRegistered) {
            return;
        }

        self::$payloadHookRegistered = true;

        Queue::createPayloadUsing(function (?string $connection, ?string $queue, array $payload): array {
            $class = $payload['data']['commandName'] ?? null;

            if (! $class || ! in_array(TenantAware::class, class_uses_recursive($class), true)) {
                return [];
            }

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
        config(['livewire.temporary_file_upload.disk' => 'tenant']);
    }
}
