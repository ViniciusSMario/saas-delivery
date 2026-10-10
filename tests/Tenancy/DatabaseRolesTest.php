<?php

use App\Modules\Establishment\Models\Setting;
use App\Platform\Backups\TenantBackups;
use App\Tenancy\SchemaManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => config(['tenancy.database_roles' => true]));

afterEach(fn () => config(['tenancy.database_roles' => false]));

it('com papel por tenant, o banco recusa ler outro schema e as tabelas da plataforma', function () {
    $a = provisionTenant('rola');
    $b = provisionTenant('rolb');
    $schemas = app(SchemaManager::class);

    expect($schemas->roleExists($a->schema_name))->toBeTrue()
        ->and(DB::connection('central')->scalar('select nspowner::regrole::text from pg_namespace where nspname = ?', [$a->schema_name]))
        ->toBe(SchemaManager::roleName($a->schema_name));

    inTenant($a, function () use ($b) {
        // O próprio schema funciona normalmente.
        Setting::write('welcome_message', 'só de A');
        expect(Setting::read('welcome_message'))->toBe('só de A')
            ->and(DB::connection('tenant')->scalar('select current_user'))->toEndWith('_role');

        // Mesmo escrevendo o schema por extenso, o banco nega.
        expect(fn () => DB::connection('tenant')->select("select * from \"{$b->schema_name}\".settings"))->toThrow(QueryException::class, 'permission denied');
        expect(fn () => DB::connection('tenant')->select('select * from public.tenants'))->toThrow(QueryException::class, 'permission denied');
    });

    // Um tenant que chega sem o papel (ex.: antes do tenants:roles) não abre: falha fechada.
    $schemas->drop($b->schema_name);
    expect(fn () => inTenant($b, fn () => null))->toThrow(\App\Tenancy\Exceptions\TenantSchemaMissing::class);
});

it('migra, faz backup, restaura e exclui com os papéis ligados', function () {
    config(['filesystems.disks.backups.root' => storage_path('framework/testing/backups-roles')]);
    Storage::forgetDisk('backups');

    $tenant = provisionTenant('rolc');
    inTenant($tenant, fn () => Setting::write('welcome_message', 'antes'));

    $backups = app(TenantBackups::class);
    $backup = $backups->create($tenant, 'manual', 'teste');
    inTenant($tenant, fn () => Setting::write('welcome_message', 'depois'));
    $backups->restore($backup, 'teste');

    expect(inTenant($tenant, fn () => Setting::read('welcome_message')))->toBe('antes')
        ->and(DB::connection('central')->scalar("select count(*) from pg_tables where schemaname = ? and tableowner <> ?", [$tenant->schema_name, SchemaManager::roleName($tenant->schema_name)]))->toBe(0);

    $this->artisan('tenants:migrate', ['--tenant' => $tenant->slug])->assertSuccessful();
    $this->get(tenantUrl($tenant, '/cardapio'))->assertOk();

    // Excluir o schema leva o papel junto.
    app(SchemaManager::class)->drop($tenant->schema_name);
    expect(app(SchemaManager::class)->roleExists($tenant->schema_name))->toBeFalse();

    File::deleteDirectory(storage_path('framework/testing/backups-roles'));
});

it('tenants:roles passa para o papel um tenant criado sem ele', function () {
    config(['tenancy.database_roles' => false]);
    $tenant = provisionTenant('rold');
    config(['tenancy.database_roles' => true]);

    $this->artisan('tenants:roles', ['--tenant' => $tenant->slug])->assertSuccessful();

    expect(app(SchemaManager::class)->roleExists($tenant->schema_name))->toBeTrue()
        ->and(inTenant($tenant, fn () => Setting::read('timezone', 'x')))->not->toBeNull();
});
