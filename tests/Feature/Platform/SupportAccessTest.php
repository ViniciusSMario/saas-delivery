<?php

use App\Livewire\Platform\Tenants\Show;
use App\Modules\Access\Audit;
use App\Modules\Access\Models\AuditLog;
use App\Platform\Models\SupportAccess;
use Livewire\Livewire;

it('a equipe entra como suporte com link de uso único e tudo fica auditado', function () {
    $tenant = provisionTenant('sup');
    $owner = createTenantUser($tenant, ['dono'], ['name' => 'Dona Maria']);
    $staff = createPlatformUser('support', ['email' => 'suporte@saas.test']);

    $this->actingAs($staff, 'platform');
    Livewire::test(Show::class, ['tenant' => $tenant])
        ->set('supportUserId', $owner->id)->set('supportReason', 'ok')->call('impersonate')->assertHasErrors('supportReason')
        ->set('supportReason', 'Chamado #42: ajuda com o cardápio')->call('impersonate')
        ->assertRedirectContains('/suporte/entrar/');

    $access = SupportAccess::where('tenant_id', $tenant->id)->sole();
    expect($access->platform_email)->toBe('suporte@saas.test')
        ->and($access->tenant_user_name)->toBe('Dona Maria')
        ->and($tenant->events()->where('type', 'support_access')->exists())->toBeTrue();

    // O token só existe no link (o banco guarda o hash): refaz o link para o teste.
    $token = 'token-de-teste-'.Str::random(20);
    $access->update(['token_hash' => hash('sha256', $token)]);
    $link = tenantUrl($tenant, '/suporte/entrar/'.$token);

    // Outro estabelecimento não aceita o link.
    $this->get(tenantUrl(tenantBySlug('teste1'), '/suporte/entrar/'.$token))->assertForbidden();

    $this->get($link)->assertRedirect(rtrim(tenantUrl($tenant, '/'), '/'));
    $this->get(tenantUrl($tenant, '/'))->assertOk()->assertSee('Modo suporte')->assertSee('suporte@saas.test');

    // Uso único.
    $this->flushSession();
    $this->get($link)->assertForbidden();

    expect($access->refresh()->used_at)->not->toBeNull();
    inTenant($tenant, function () use ($owner) {
        $login = AuditLog::where('action', 'support.login')->sole();
        expect($login->user_id)->toBe($owner->id)->and($login->changes['equipe'])->toBe('suporte@saas.test');
    });
});

it('marca na auditoria o que foi feito em modo suporte e encerra ao sair', function () {
    $tenant = provisionTenant('sup2');
    $owner = createTenantUser($tenant, ['dono']);
    $token = 'tok-'.Str::random(30);
    SupportAccess::create([
        'tenant_id' => $tenant->id, 'platform_email' => 'equipe@saas.test', 'tenant_user_id' => $owner->id,
        'reason' => 'Ajuste de configuração', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinute(),
    ]);

    $this->get(tenantUrl($tenant, '/suporte/entrar/'.$token))->assertRedirect();

    // Qualquer registro de auditoria durante a sessão leva a marca.
    $this->post(tenantUrl($tenant, '/suporte/sair'))->assertRedirectContains('admin.localhost');

    inTenant($tenant, fn () => expect(AuditLog::where('action', 'support.logout')->value('changes'))->toMatchArray(['equipe' => 'equipe@saas.test']));
    expect(SupportAccess::where('tenant_id', $tenant->id)->sole()->ended_at)->not->toBeNull();

    $this->get(tenantUrl($tenant, '/'))->assertRedirect(); // deslogado
});

it('recusa link vencido', function () {
    $tenant = provisionTenant('sup3');
    $owner = createTenantUser($tenant, ['dono']);
    SupportAccess::create([
        'tenant_id' => $tenant->id, 'platform_email' => 'equipe@saas.test', 'tenant_user_id' => $owner->id,
        'reason' => 'Teste vencido', 'token_hash' => hash('sha256', 'vencido'), 'expires_at' => now()->subSecond(),
    ]);

    $this->get(tenantUrl($tenant, '/suporte/entrar/vencido'))->assertForbidden();
});

it('Audit marca via_suporte durante a sessão de suporte', function () {
    $tenant = provisionTenant('sup4');
    $owner = createTenantUser($tenant, ['dono']);

    activateTenant($tenant);
    $this->actingAs($owner);
    request()->setLaravelSession($session = app('session')->driver());
    $session->put('support_access', ['id' => 1, 'email' => 'equipe@saas.test', 'user' => $owner->name]);

    $log = Audit::record('teste.acao', null, ['x' => 1]);

    expect($log->changes)->toMatchArray(['x' => 1, 'via_suporte' => 'equipe@saas.test']);
});
