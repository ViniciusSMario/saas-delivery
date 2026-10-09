<?php

namespace App\Tenancy\Actions;

use App\Tenancy\Enums\TenantStatus;
use App\Tenancy\Exceptions\InvalidTenantData;
use App\Tenancy\Models\Tenant;
use App\Tenancy\SchemaManager;
use App\Tenancy\TenantMigrator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cria um estabelecimento: registro "provisioning" no public, schema, migrations,
 * seed e só então "active". Qualquer falha remove o schema e marca "failed".
 */
class ProvisionTenant
{
    public const SLUG_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/';

    public function __construct(
        private readonly SchemaManager $schemas,
        private readonly TenantMigrator $migrator,
    ) {
    }

    public function handle(string $slug, string $name, ?string $domain = null, ?string $document = null, ?int $planId = null): Tenant
    {
        $slug = mb_strtolower(trim($slug));
        $domain = mb_strtolower(trim($domain ?? $slug.'.'.config('tenancy.base_domain')));

        $this->validate($slug, $domain);

        $tenant = DB::connection('central')->transaction(function () use ($slug, $name, $domain, $document, $planId) {
            $tenant = Tenant::create([
                'slug' => $slug,
                'name' => $name,
                'document' => $document,
                'plan_id' => $planId,
                'status' => TenantStatus::Provisioning,
            ]);

            $tenant->domains()->create(['domain' => $domain, 'type' => 'subdomain', 'is_primary' => true]);
            $tenant->recordEvent('created', ['domain' => $domain]);

            return $tenant;
        });

        try {
            $this->schemas->create($tenant->schema_name);
            $this->migrator->migrate($tenant);
            $this->migrator->seed($tenant);
        } catch (Throwable $e) {
            $this->schemas->drop($tenant->schema_name);
            $tenant->update(['status' => TenantStatus::Failed]);
            $tenant->recordEvent('provisioning_failed', ['error' => $e->getMessage()]);

            throw $e;
        }

        $tenant->update(['status' => TenantStatus::Active, 'activated_at' => now()]);
        $tenant->recordEvent('activated');

        return $tenant->refresh();
    }

    private function validate(string $slug, string $domain): void
    {
        if (! preg_match(self::SLUG_PATTERN, $slug)) {
            throw new InvalidTenantData("Slug inválido [{$slug}]: use 3 a 40 letras minúsculas, números ou hífen.");
        }

        $central = DB::connection('central');

        if ($central->table('reserved_slugs')->where('slug', $slug)->exists()) {
            throw new InvalidTenantData("O slug [{$slug}] é reservado.");
        }

        if ($central->table('tenants')->where('slug', $slug)->exists()) {
            throw new InvalidTenantData("O slug [{$slug}] já está em uso.");
        }

        if ($central->table('tenant_domains')->where('domain', $domain)->exists()) {
            throw new InvalidTenantData("O domínio [{$domain}] já está em uso.");
        }

        if (in_array($domain, config('tenancy.central_domains'), true)) {
            throw new InvalidTenantData("O domínio [{$domain}] é um domínio central.");
        }
    }
}
