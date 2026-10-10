<?php

use App\Livewire\Tenant\Domains;
use App\Platform\Billing\Subscriptions;
use App\Platform\Models\Plan;
use App\Tenancy\CustomDomains;
use App\Tenancy\Models\TenantDomain;
use Livewire\Livewire;

afterEach(fn () => CustomDomains::$resolver = null);

it('cadastra, verifica pelo TXT e passa a responder no domínio próprio', function () {
    $tenant = provisionTenant('dom');
    $owner = createTenantUser($tenant, ['dono']);
    activateTenant($tenant);
    $this->actingAs($owner);

    Livewire::test(Domains::class)
        ->set('domain', 'teste1.localhost')->call('add')->assertHasErrors('domain')    // endereço do sistema
        ->set('domain', 'não é domínio')->call('add')->assertHasErrors('domain')
        ->set('domain', 'https://Pedidos.Cliente-Teste.com.br/')->call('add')->assertHasNoErrors();

    $domain = TenantDomain::where('domain', 'pedidos.cliente-teste.com.br')->sole();
    expect($domain->verified_at)->toBeNull()->and($domain->verification_token)->toStartWith('saas-');

    app(\App\Tenancy\TenantContext::class)->reset();

    // Antes de verificar, o domínio não abre o sistema.
    $this->get('http://pedidos.cliente-teste.com.br/cardapio')->assertNotFound();

    // DNS sem o TXT: continua pendente.
    CustomDomains::$resolver = fn (string $name, string $type) => $type === 'CNAME' ? [$tenant->primaryDomain().'.'] : [];
    activateTenant($tenant);
    Livewire::test(Domains::class)->call('verify', $domain->id)->assertSee('Ainda não encontramos o registro TXT');
    expect($domain->refresh()->verified_at)->toBeNull();

    // Com o TXT certo: verificado.
    CustomDomains::$resolver = fn (string $name, string $type) => $type === 'TXT' && $name === '_saas-verify.pedidos.cliente-teste.com.br' ? ['"'.$domain->verification_token.'"'] : [];
    Livewire::test(Domains::class)->call('verify', $domain->id)->call('makePrimary', $domain->id);

    expect($domain->refresh()->verified_at)->not->toBeNull()
        ->and($domain->is_primary)->toBeTrue()
        ->and($tenant->primaryDomain())->toBe('pedidos.cliente-teste.com.br');

    app(\App\Tenancy\TenantContext::class)->reset();
    $this->get('http://pedidos.cliente-teste.com.br/cardapio')->assertOk();

    // Remover volta o endereço do sistema como principal.
    activateTenant($tenant);
    Livewire::test(Domains::class)->call('remove', $domain->id);
    expect($tenant->primaryDomain())->not->toBe('pedidos.cliente-teste.com.br');
});

it('não deixa usar domínio de outro estabelecimento nem usar como principal sem verificar', function () {
    $a = provisionTenant('doma');
    $b = provisionTenant('domb');
    $service = app(CustomDomains::class);

    $domain = $service->add($a, 'loja.exemplo.com.br');

    expect(fn () => $service->add($b, 'loja.exemplo.com.br'))->toThrow(\Illuminate\Validation\ValidationException::class, 'já está cadastrado')
        ->and(fn () => $service->makePrimary($domain))->toThrow(\Illuminate\Validation\ValidationException::class, 'Verifique');
});

it('domínio próprio depende do plano', function () {
    $tenant = provisionTenant('domplan');
    app(Subscriptions::class)->startTrial($tenant, Plan::where('slug', 'essencial')->sole(), 'x@y.com');
    activateTenant($tenant);
    $this->actingAs(createTenantUser($tenant, ['dono']));

    Livewire::test(Domains::class)->assertSee('não está no seu plano')->set('domain', 'a.exemplo.com.br')->call('add')->assertForbidden();
});
