<?php

use App\Modules\Establishment\Models\Setting;
use App\Tenancy\Actions\ProvisionTenant;
use App\Tenancy\Enums\TenantStatus;
use App\Tenancy\Exceptions\InvalidTenantData;
use App\Tenancy\Exceptions\InvalidTenantSchema;
use App\Tenancy\Models\Tenant;
use App\Tenancy\SchemaManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\FailingSeeder;

it('provisiona um tenant ativo, com schema gerado pelo sistema, migrado e com seed', function () {
    $this->artisan('tenants:create', ['slug' => 'pizzaria-nova', 'name' => 'Pizzaria Nova'])->assertSuccessful();

    $tenant = tenantBySlug('pizzaria-nova');

    expect($tenant->status)->toBe(TenantStatus::Active)
        ->and($tenant->schema_name)->toMatch(SchemaManager::PATTERN)->not->toContain('pizzaria')
        ->and($tenant->primaryDomain())->toBe('pizzaria-nova.localhost')
        ->and($tenant->schema_version)->toBe(latestTenantMigration())
        ->and(app(SchemaManager::class)->exists($tenant->schema_name))->toBeTrue()
        ->and(inTenant($tenant, fn () => Setting::read('currency')))->toBe('BRL')
        ->and($tenant->events()->pluck('type')->all())->toContain('created', 'activated');
});

it('não deixa schema para trás quando o provisionamento falha', function () {
    config(['tenancy.seeder' => FailingSeeder::class]);

    expect(fn () => app(ProvisionTenant::class)->handle('quebrado', 'Quebrado'))
        ->toThrow(RuntimeException::class);

    $tenant = tenantBySlug('quebrado');

    expect($tenant->status)->toBe(TenantStatus::Failed)
        ->and(app(SchemaManager::class)->exists($tenant->schema_name))->toBeFalse()
        ->and($tenant->events()->where('type', 'provisioning_failed')->exists())->toBeTrue();

    $this->get(tenantUrl($tenant))->assertNotFound();
});

it('valida slug: formato, reservado e duplicado', function (string $slug) {
    expect(fn () => app(ProvisionTenant::class)->handle($slug, 'X'))->toThrow(InvalidTenantData::class);
})->with(['A B', '-abc', 'ab', 'admin', 'cardapio', 'teste1', 'tenant"; drop']);

it('mantém o nome do schema imutável', function () {
    $tenant = tenantBySlug('teste1');

    expect(fn () => $tenant->update(['schema_name' => 'tenant_outronome']))->toThrow(LogicException::class);
});

it('recusa nomes de schema fora do padrão no SchemaManager', function (string $schema) {
    expect(fn () => app(SchemaManager::class)->switchTo($schema))->toThrow(InvalidTenantSchema::class);
})->with(['public', 'tenant_none', 'tenant_ABCDEF', 'tenant_abc"def', 'tenant_abcdef,public', 'tenant_abcdef_old_20261009']);

it('continua migrando os outros tenants quando um falha', function () {
    $broken = provisionTenant('mig');
    $healthy = provisionTenant('mig');

    foreach ([$broken, $healthy] as $tenant) {
        // Desfaz todas as migrations do tenant.
        $this->artisan('tenants:rollback', ['--tenant' => $tenant->slug, '--step' => count(glob(base_path('database/migrations/tenant/*.php')))])->assertSuccessful();
        expect(inTenant($tenant, fn () => Schema::hasTable('settings')))->toBeFalse();
    }

    // Uma tabela conflitante faz a migration de settings quebrar só no tenant "broken".
    inTenant($broken, fn () => DB::statement('create table settings (id int)'));

    $this->artisan('tenants:migrate')->assertFailed();

    expect(inTenant($healthy, fn () => Schema::hasColumn('settings', 'key')))->toBeTrue()
        ->and(inTenant($broken, fn () => Schema::hasColumn('settings', 'key')))->toBeFalse()
        ->and($broken->events()->where('type', 'migrated_failed')->exists())->toBeTrue()
        ->and($healthy->fresh()->schema_version)->toBe(latestTenantMigration());

    // Corrigido o problema, o tenant volta a migrar normalmente.
    inTenant($broken, fn () => DB::statement('drop table settings'));
    $this->artisan('tenants:migrate', ['--tenant' => $broken->slug])->assertSuccessful();
});

it('lista tenants e migrations pendentes em tenants:status', function () {
    $this->artisan('tenants:status')
        ->expectsOutputToContain('teste1')
        ->assertSuccessful();
});

it('nunca coloca public no search_path do tenant', function () {
    $path = inTenant(tenantBySlug('teste1'), fn () => DB::connection('tenant')->scalar('show search_path'));

    expect($path)->not->toContain('public')->toContain('extensions');
    expect(Tenant::count())->toBeGreaterThan(0); // conexão central segue em public
});
