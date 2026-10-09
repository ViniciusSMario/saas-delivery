<?php

use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\User;
use App\Platform\Models\PlatformUser;
use App\Tenancy\Actions\ProvisionTenant;
use App\Tenancy\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;

pest()->extend(Tests\TestCase::class)->in('Feature', 'Unit', 'Tenancy', 'Arch');

/** Senha usada pelos usuários criados nos testes. */
const TEST_PASSWORD = 'senha-teste-123';

/** Um dos tenants de referência criados no início da suíte (teste1, teste2). */
function tenantBySlug(string $slug): Tenant
{
    return Tenant::where('slug', $slug)->firstOrFail();
}

/** Provisiona um tenant novo, com slug único, para testes que alteram o tenant. */
function provisionTenant(string $prefix = 't'): Tenant
{
    return app(ProvisionTenant::class)->handle($prefix.Str::lower(Str::random(8)), 'Tenant '.$prefix);
}

/**
 * @template T
 *
 * @param  Closure(Tenant): T  $callback
 * @return T
 */
function inTenant(Tenant $tenant, Closure $callback): mixed
{
    return app(TenantContext::class)->run($tenant, $callback);
}

/** Ativa o tenant como se fosse a requisição (para Livewire::test e chamadas diretas). */
function activateTenant(Tenant $tenant): void
{
    app(TenantContext::class)->activate($tenant);
}

function tenantUrl(Tenant $tenant, string $path = '/'): string
{
    return 'http://'.$tenant->primaryDomain().$path;
}

/** Cria um usuário no schema do tenant com os perfis (slugs) indicados. */
function createTenantUser(Tenant $tenant, array $roles = ['dono'], array $attributes = []): User
{
    return inTenant($tenant, function () use ($roles, $attributes) {
        $user = User::create($attributes + [
            'name' => 'Usuário '.Str::random(5),
            'email' => Str::lower(Str::random(10)).'@teste.com',
            'password' => TEST_PASSWORD,
        ]);

        $user->roles()->sync(Role::whereIn('slug', $roles)->pluck('id'));

        return $user->load('roles');
    });
}

function createPlatformUser(string $role = PlatformUser::ROLE_ADMIN, array $attributes = []): PlatformUser
{
    return PlatformUser::create($attributes + [
        'name' => 'Equipe '.Str::random(4),
        'email' => Str::lower(Str::random(10)).'@saas.test',
        'password' => TEST_PASSWORD,
        'role' => $role,
    ]);
}

/** Nome da última migration de tenant (o schema_version esperado). */
function latestTenantMigration(): string
{
    $files = glob(base_path('database/migrations/tenant/*.php'));
    sort($files);

    return pathinfo(end($files), PATHINFO_FILENAME);
}
