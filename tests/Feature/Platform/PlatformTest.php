<?php

use App\Livewire\Platform\Auth\Login;
use App\Livewire\Platform\Auth\TwoFactor;
use App\Livewire\Platform\Tenants\Create;
use App\Livewire\Platform\Tenants\Index;
use App\Modules\Access\Models\User;
use App\Modules\Access\Notifications\SetPasswordNotification;
use App\Platform\Models\PlatformUser;
use App\Platform\TwoFactor as TwoFactorEngine;
use App\Tenancy\Enums\TenantStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

const ADMIN = 'http://admin.localhost';

it('responde no domínio do admin sem tratar como tenant', function () {
    $this->get(ADMIN.'/login')->assertOk()->assertSee('Admin da plataforma');
    $this->get(ADMIN.'/')->assertRedirect(ADMIN.'/login');
});

it('exige 2FA: senha certa só deixa o login pendente', function () {
    $user = createPlatformUser();

    Livewire::test(Login::class)
        ->set('email', $user->email)->set('password', TEST_PASSWORD)
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('platform.two-factor'));

    expect(Auth::guard('platform')->check())->toBeFalse()
        ->and(session(Login::PENDING_KEY))->toBe($user->id);
});

it('configura o 2FA no primeiro acesso e só então entra', function () {
    $user = createPlatformUser();
    session([Login::PENDING_KEY => $user->id]);

    $component = Livewire::test(TwoFactor::class)->assertSee('escaneie o QR code');
    $secret = session(TwoFactor::SETUP_SECRET_KEY);

    $component->set('code', '000000')->call('verify')->assertHasErrors(['code']);
    expect(Auth::guard('platform')->check())->toBeFalse();

    $component->set('code', app(TwoFactorEngine::class)->currentCode($secret))
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('platform.tenants.index'));

    expect(Auth::guard('platform')->id())->toBe($user->id)
        ->and($user->fresh()->hasTwoFactor())->toBeTrue()
        ->and($user->fresh()->two_factor_secret)->toBe($secret);
});

it('pede o código do autenticador quando o 2FA já está configurado', function () {
    $engine = app(TwoFactorEngine::class);
    $secret = $engine->generateSecret();
    $user = createPlatformUser(attributes: ['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()]);
    session([Login::PENDING_KEY => $user->id]);

    Livewire::test(TwoFactor::class)
        ->assertDontSee('escaneie')
        ->set('code', $engine->currentCode($secret))
        ->call('verify')
        ->assertRedirect(route('platform.tenants.index'));
});

it('não abre a tela de 2FA sem a etapa de senha', function () {
    Livewire::test(TwoFactor::class)->assertRedirect(route('platform.login'));
});

it('suspende e reativa um estabelecimento, com efeito imediato no app', function () {
    $tenant = provisionTenant('adm');
    $admin = createPlatformUser();

    Livewire::actingAs($admin, 'platform')->test(Index::class)->call('suspend', $tenant->id)->assertHasNoErrors();

    expect($tenant->fresh()->status)->toBe(TenantStatus::Suspended)
        ->and($tenant->events()->where('type', 'suspended')->value('actor'))->toBe($admin->email);
    $this->get(tenantUrl($tenant, '/login'))->assertForbidden()->assertSee('Conta suspensa');

    Livewire::actingAs($admin, 'platform')->test(Index::class)->call('reactivate', $tenant->id)->assertHasNoErrors();

    expect($tenant->fresh()->status)->toBe(TenantStatus::Active);
    $this->get(tenantUrl($tenant, '/login'))->assertOk();
});

it('deixa o suporte só consultar', function () {
    $tenant = tenantBySlug('teste1');
    $support = createPlatformUser(PlatformUser::ROLE_SUPPORT);

    // Com busca: a suíte cria muitos estabelecimentos e a lista é paginada.
    $this->actingAs($support, 'platform')->get(ADMIN.'/?q=teste1')->assertOk()->assertSee('Tenant Teste 1');
    $this->actingAs($support, 'platform')->get(ADMIN.'/tenants/'.$tenant->id)->assertOk();
    $this->actingAs($support, 'platform')->get(ADMIN.'/tenants/novo')->assertForbidden();

    Livewire::actingAs($support, 'platform')->test(Index::class)->call('suspend', $tenant->id)->assertForbidden();
    expect($tenant->fresh()->status)->toBe(TenantStatus::Active);
});

it('cria estabelecimento com dono pelo admin', function () {
    Notification::fake();
    $admin = createPlatformUser();

    Livewire::actingAs($admin, 'platform')->test(Create::class)
        ->set('name', 'Lanchonete Nova')
        ->assertSet('slug', 'lanchonete-nova')
        ->set('owner_name', 'Joana Dona')
        ->set('owner_email', 'joana@lanchonete.com')
        ->call('save')
        ->assertHasNoErrors();

    $tenant = tenantBySlug('lanchonete-nova');
    $owner = inTenant($tenant, fn () => User::with('roles')->where('email', 'joana@lanchonete.com')->firstOrFail());

    expect($tenant->status)->toBe(TenantStatus::Active)
        ->and($owner->isOwner())->toBeTrue()
        ->and($tenant->events()->where('type', 'onboarded')->value('actor'))->toBe($admin->email);

    Notification::assertSentTo($owner, SetPasswordNotification::class, fn ($n) => $n->invitation);
});

it('recusa slug reservado ou repetido no cadastro pelo admin', function (string $slug) {
    Livewire::actingAs(createPlatformUser(), 'platform')->test(Create::class)
        ->set('name', 'X')->set('slug', $slug)
        ->set('owner_name', 'Dono')->set('owner_email', 'dono@x.com')
        ->call('save')->assertHasErrors(['slug']);
})->with(['admin', 'teste1', 'A_B']);

it('separa as sessões da equipe e dos estabelecimentos', function () {
    $admin = createPlatformUser();
    $owner = createTenantUser(tenantBySlug('teste1'));

    $this->actingAs($admin, 'platform')->get(tenantUrl(tenantBySlug('teste1'), '/'))->assertRedirect();
    Auth::forgetGuards();
    $this->actingAs($owner)->get(ADMIN.'/')->assertRedirect(ADMIN.'/login');
});

it('roteia as ações Livewire do admin pelo grupo da plataforma e as do tenant pelo grupo do tenant', function () {
    $routes = app('router')->getRoutes();

    $admin = $routes->match(Request::create(ADMIN.'/livewire/update', 'POST'));
    $tenant = $routes->match(Request::create('http://teste1.localhost/livewire/update', 'POST'));

    expect($admin->getName())->toBe('platform.livewire.update')
        ->and($admin->gatherMiddleware())->toContain('platform')
        ->and($tenant->getName())->toBe('livewire.update')
        ->and($tenant->gatherMiddleware())->toContain('tenant');
});
