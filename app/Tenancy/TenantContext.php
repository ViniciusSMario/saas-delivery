<?php

namespace App\Tenancy;

use App\Tenancy\Exceptions\TenantAlreadyActive;
use App\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Storage;

/**
 * Ponto único de verdade sobre o tenant ativo no processo.
 * Ativado só pelo middleware HTTP, pelo middleware de jobs e pelos comandos tenants:*.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    private readonly string $baseCachePrefix;

    public function __construct(private readonly SchemaManager $schemas)
    {
        $this->baseCachePrefix = (string) config('cache.prefix');
    }

    /** Ativa o tenant da requisição. Um segundo tenant na mesma requisição é erro. */
    public function activate(Tenant $tenant): void
    {
        if ($this->tenant !== null) {
            if ($this->tenant->is($tenant)) {
                return;
            }

            throw new TenantAlreadyActive($this->tenant, $tenant);
        }

        $this->switchTo($tenant);
    }

    /**
     * Executa $callback dentro de $tenant e restaura o estado anterior no fim.
     *
     * @template T
     *
     * @param  Closure(Tenant): T  $callback
     * @return T
     */
    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;

        if ($previous?->is($tenant)) {
            return $callback($tenant);
        }

        $this->switchTo($tenant);

        try {
            return $callback($tenant);
        } finally {
            $previous ? $this->switchTo($previous) : $this->reset();
        }
    }

    /** Volta ao estado fechado: sem tenant, conexão em schema inexistente. */
    public function reset(): void
    {
        $this->tenant = null;
        $this->schemas->reset();
        $this->applyAuxiliaryContext(null);
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function id(): ?string
    {
        return $this->tenant?->getKey();
    }

    private function switchTo(Tenant $tenant): void
    {
        // O schema vem sempre do registro em public.tenants, nunca da URL.
        $this->schemas->switchTo($tenant->schema_name);
        $this->tenant = $tenant;
        $this->applyAuxiliaryContext($tenant);
    }

    /** Cache, disco de arquivos e log separados por tenant. */
    private function applyAuxiliaryContext(?Tenant $tenant): void
    {
        $id = $tenant?->getKey();

        config(['cache.prefix' => $id ? $this->baseCachePrefix."tenant:{$id}:" : $this->baseCachePrefix]);
        Cache::forgetDriver(config('cache.default'));

        // Sem tenant o disco "tenant" não existe: usá-lo fora de um tenant é erro.
        config(['filesystems.disks.tenant' => $id ? [
            'driver' => 'local',
            'root' => storage_path("app/tenants/{$id}"),
            'throw' => true,
        ] : null]);
        Storage::forgetDisk('tenant');

        $id ? Context::add('tenant_id', $id) : Context::forget('tenant_id');
    }
}
