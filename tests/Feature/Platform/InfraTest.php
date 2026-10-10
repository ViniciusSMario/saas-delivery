<?php

use App\Tenancy\Enums\TenantStatus;

it('responde a saúde em qualquer host', function () {
    $this->getJson('http://localhost/saude')->assertOk()->assertJson(['status' => 'ok', 'checks' => ['database' => true, 'redis' => true, 'storage' => true]]);
    $this->getJson(tenantUrl(tenantBySlug('teste1'), '/saude'))->assertOk();
});

it('só libera certificado para domínios do sistema, e só para o Caddy', function () {
    config(['platform.caddy_ask_token' => 'segredo']);
    $ask = fn (string $domain, ?string $token = 'segredo') => $this->get('http://localhost/caddy/ask?domain='.$domain, $token ? ['X-Caddy-Ask' => $token] : []);

    $ask('teste1.localhost', null)->assertForbidden();
    $ask('teste1.localhost', 'errado')->assertForbidden();

    $ask('teste1.localhost')->assertOk();
    $ask('admin.localhost')->assertOk();
    $ask('localhost')->assertOk();
    $ask('naoexiste.localhost')->assertNotFound();
    $ask('google.com')->assertNotFound();

    $tenant = provisionTenant('ask');
    $tenant->domains()->create(['domain' => 'pedidos.cliente.com.br', 'type' => 'custom']);
    $ask('pedidos.cliente.com.br')->assertNotFound(); // ainda não verificado

    $tenant->domains()->where('domain', 'pedidos.cliente.com.br')->update(['verified_at' => now()]);
    $ask('pedidos.cliente.com.br')->assertOk();

    $tenant->update(['status' => TenantStatus::Deleted]);
    $ask($tenant->primaryDomain())->assertNotFound();
});

it('sem token configurado, ninguém consulta', function () {
    config(['platform.caddy_ask_token' => '']);
    $this->get('http://localhost/caddy/ask?domain=teste1.localhost', ['X-Caddy-Ask' => ''])->assertForbidden();
});
