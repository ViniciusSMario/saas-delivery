<?php

use App\Tenancy\TenantModel;
use Symfony\Component\Finder\Finder;

arch('módulos de negócio não usam a plataforma')
    ->expect('App\Modules')
    ->not->toUse('App\Platform');

arch('a camada de tenancy não depende de módulos de negócio')
    ->expect('App\Tenancy')
    ->not->toUse('App\Modules');

arch('telas do estabelecimento não usam a plataforma')
    ->expect('App\Livewire\Tenant')
    ->not->toUse('App\Platform');

arch('módulos de negócio não dependem da camada de interface')
    ->expect('App\Modules')
    ->not->toUse(['App\Livewire', 'App\Http\Controllers']);

arch('não há debug esquecido')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

/** @return array<string, string> caminho relativo => conteúdo */
function phpFilesIn(string $directory): array
{
    $files = [];

    foreach (Finder::create()->files()->name('*.php')->in(base_path($directory)) as $file) {
        $files[str_replace('\\', '/', $directory.'/'.$file->getRelativePathname())] = $file->getContents();
    }

    return $files;
}

it('só o SchemaManager menciona search_path', function () {
    $offenders = collect(phpFilesIn('app'))
        ->filter(fn (string $code) => str_contains(mb_strtolower($code), 'search_path'))
        ->keys()
        ->reject(fn (string $path) => $path === 'app/Tenancy/SchemaManager.php')
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

it('só o SchemaManager escreve SQL de schema', function () {
    $offenders = collect(phpFilesIn('app'))
        ->filter(fn (string $code) => preg_match('/(create|drop|alter)\s+schema|set\s+role/i', $code))
        ->keys()
        ->reject(fn (string $path) => $path === 'app/Tenancy/SchemaManager.php')
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

it('módulos não usam nome de schema qualificado', function () {
    $offenders = collect(phpFilesIn('app/Modules'))
        ->filter(fn (string $code) => preg_match('/\b(public|tenant_[a-z0-9_]+)\.[a-z_]+/i', $code))
        ->keys()
        ->all();

    expect($offenders)->toBe([]);
});

it('models de módulo vivem na conexão do tenant', function () {
    $models = collect(phpFilesIn('app/Modules'))
        ->keys()
        ->filter(fn (string $path) => str_contains($path, '/Models/'))
        ->map(fn (string $path) => 'App\\'.str_replace(['app/', '/', '.php'], ['', '\\', ''], $path));

    expect($models)->not->toBeEmpty();

    foreach ($models as $class) {
        // TenantModel, ou um Authenticatable (User) que fixa a conexão "tenant".
        $usesTenant = is_subclass_of($class, TenantModel::class) || (new $class)->getConnectionName() === 'tenant';

        expect($usesTenant)->toBeTrue("{$class} deve usar a conexão tenant (estenda TenantModel)");
    }
});
