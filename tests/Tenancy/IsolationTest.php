<?php

use App\Modules\Establishment\Models\Setting;
use App\Tenancy\Enums\TenantStatus;
use App\Tenancy\Exceptions\TenantAlreadyActive;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->a = tenantBySlug('teste1');
    $this->b = tenantBySlug('teste2');
});

it('não mostra em B um dado criado em A', function () {
    $secret = 'segredo-'.Str::random(10);

    inTenant($this->a, fn () => Setting::write('welcome_message', $secret));

    expect(inTenant($this->b, fn () => Setting::whereRaw('value::text like ?', ["%{$secret}%"])->exists()))->toBeFalse()
        ->and(inTenant($this->a, fn () => Setting::read('welcome_message')))->toBe($secret);

    $this->get(tenantUrl($this->a, '/cardapio'))->assertOk()->assertSee($secret);
    $this->get(tenantUrl($this->b, '/login'))->assertOk()->assertDontSee($secret);
    $this->get(tenantUrl($this->b, '/cardapio'))->assertOk()->assertDontSee($secret);
    $this->get('http://localhost/cardapio/teste2')->assertOk()->assertDontSee($secret);
});

it('responde 404 para host desconhecido sem nenhuma query de tenant', function () {
    $tenantQueries = 0;
    DB::listen(function ($query) use (&$tenantQueries) {
        if ($query->connectionName === 'tenant') {
            $tenantQueries++;
        }
    });

    $this->get('http://naoexiste.localhost/')->assertNotFound();
    $this->get('http://localhost/cardapio/naoexiste')->assertNotFound();

    expect($tenantQueries)->toBe(0);
});

it('bloqueia tenant suspenso e não expõe tenant em provisionamento', function () {
    $tenant = provisionTenant('susp');

    $tenant->update(['status' => TenantStatus::Suspended]);
    $this->get(tenantUrl($tenant))->assertForbidden()->assertSee('Conta suspensa');

    $tenant->update(['status' => TenantStatus::Provisioning]);
    $this->get(tenantUrl($tenant))->assertNotFound();

    $tenant->update(['status' => TenantStatus::PendingDeletion]);
    $this->get(tenantUrl($tenant))->assertNotFound();
});

it('recusa em B a sessão criada em A', function () {
    $this->withSession([
        'tenant_id' => $this->a->id,
        'dado_de_a' => 'nao-pode-vazar',
    ])->get(tenantUrl($this->b, '/cardapio'))->assertOk();

    expect(session('dado_de_a'))->toBeNull()
        ->and(session('tenant_id'))->toBe($this->b->id);
});

it('mantém a sessão do próprio tenant', function () {
    $this->withSession(['tenant_id' => $this->a->id, 'carrinho' => 'x'])
        ->get(tenantUrl($this->a, '/cardapio'))
        ->assertOk()
        ->assertSessionHas('carrinho', 'x');
});

it('lança exceção para query sem tenant ativo', function () {
    expect(app(TenantContext::class)->check())->toBeFalse()
        ->and(DB::connection('tenant')->scalar('select current_schema()'))->toBeNull();

    expect(fn () => Setting::count())->toThrow(QueryException::class);
    expect(fn () => DB::table('settings')->get())->toThrow(QueryException::class);
});

it('volta ao estado fechado depois de run() e depois da requisição', function () {
    inTenant($this->a, fn () => expect(Setting::count())->toBeGreaterThan(0));
    expect(fn () => Setting::count())->toThrow(QueryException::class);

    $this->get(tenantUrl($this->a, '/login'))->assertOk();
    expect(app(TenantContext::class)->check())->toBeFalse();
    expect(fn () => Setting::count())->toThrow(QueryException::class);
});

it('não permite ativar um segundo tenant na mesma requisição', function () {
    $context = app(TenantContext::class);
    $context->activate($this->a);

    expect(fn () => $context->activate($this->b))->toThrow(TenantAlreadyActive::class);

    // run() troca e restaura
    $schemaInB = $context->run($this->b, fn () => DB::connection('tenant')->scalar('select current_schema()'));
    expect($schemaInB)->toBe($this->b->schema_name)
        ->and(DB::connection('tenant')->scalar('select current_schema()'))->toBe($this->a->schema_name);

    $context->reset();
});

it('separa chaves de cache e arquivos por tenant', function () {
    inTenant($this->a, fn () => Cache::put('chave', 'valor-a', 60));
    inTenant($this->b, fn () => Cache::put('chave', 'valor-b', 60));

    expect(inTenant($this->a, fn () => Cache::get('chave')))->toBe('valor-a')
        ->and(inTenant($this->b, fn () => Cache::get('chave')))->toBe('valor-b')
        ->and(Cache::get('chave'))->toBeNull();

    $prefixA = inTenant($this->a, fn () => Cache::getPrefix());
    $prefixB = inTenant($this->b, fn () => Cache::getPrefix());
    expect($prefixA)->not->toBe($prefixB)->toContain($this->a->id);

    $pathA = inTenant($this->a, fn () => Storage::disk('tenant')->path('logo.png'));
    $pathB = inTenant($this->b, fn () => Storage::disk('tenant')->path('logo.png'));
    expect($pathA)->not->toBe($pathB)->toContain($this->a->id);

    expect(fn () => Storage::disk('tenant')->path('logo.png'))->toThrow(InvalidArgumentException::class);
});

it('não aceita requisição Livewire fora de um tenant', function () {
    $this->post('http://localhost/livewire/update', ['components' => []])->assertNotFound();
    $this->post('http://naoexiste.localhost/livewire/update', ['components' => []])->assertNotFound();
});

it('resolve e ativa o tenant antes de iniciar a sessão', function () {
    $route = app('router')->getRoutes()->getByName('dashboard');
    $stack = app('router')->gatherRouteMiddleware($route);

    expect(array_slice($stack, 0, 2))->toBe([
        App\Tenancy\Middleware\IdentifyTenant::class,
        App\Tenancy\Middleware\EnsureTenantIsActive::class,
    ])->and(array_search(Illuminate\Session\Middleware\StartSession::class, $stack))
        ->toBeLessThan(array_search(App\Tenancy\Middleware\EnsureSessionBelongsToTenant::class, $stack));
});

it('usa um store de sessão com prefixo fixo, que não muda com o tenant ativo', function () {
    // Regressão: com o prefixo do tenant, a sessão podia ser gravada numa chave e lida em
    // outra, dependendo de quando o driver de sessão era criado (419 no Livewire).
    $outside = Cache::store('sessions')->getPrefix();

    expect(config('session.store'))->toBe('sessions')
        ->and(inTenant($this->a, fn () => Cache::store('sessions')->getPrefix()))->toBe($outside)
        ->and($outside)->not->toContain($this->a->id);
});
