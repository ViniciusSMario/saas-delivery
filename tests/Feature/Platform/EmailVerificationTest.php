<?php

use App\Livewire\Platform\Tenants\Show;
use App\Platform\Notifications\TenantNotice;
use App\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(fn () => RateLimiter::clear('signup:127.0.0.1'));

function signUp(): Tenant
{
    test()->post('http://localhost/cadastro', [
        'name' => 'Lanchonete Confirma', 'slug' => 'confirma-'.Str::lower(Str::random(5)), 'owner_name' => 'Rui',
        'email' => 'rui-'.Str::lower(Str::random(5)).'@exemplo.com', 'password' => 'senha-forte-123', 'password_confirmation' => 'senha-forte-123',
        'plan' => 'profissional', 'terms' => '1',
    ])->assertRedirect();

    return Tenant::latest('created_at')->first();
}

it('o cardápio só vai ao ar depois que o dono confirma o e-mail', function () {
    Notification::fake();
    $tenant = signUp();

    expect($tenant->email_verified_at)->toBeNull();
    $this->get(tenantUrl($tenant, '/cardapio'))->assertForbidden()->assertSee('Cardápio em preparação');
    $this->post(tenantUrl($tenant, '/cardapio/pedido'), [])->assertForbidden();

    // O painel funciona e avisa o dono.
    $owner = inTenant($tenant, fn () => \App\Modules\Access\Models\User::sole());
    // Primeiro acesso: o assistente abre e também mostra o aviso.
    $this->actingAs($owner)->get(tenantUrl($tenant, '/'))->assertRedirect(tenantUrl($tenant, '/primeiros-passos'));
    $this->actingAs($owner)->get(tenantUrl($tenant, '/primeiros-passos'))->assertOk()->assertSee('Confirme seu e-mail');
    skipOnboarding($tenant);
    $this->actingAs($owner)->get(tenantUrl($tenant, '/'))->assertOk()->assertSee('Confirme seu e-mail');

    // O e-mail traz o link assinado.
    $link = null;
    Notification::assertSentOnDemand(TenantNotice::class, function ($notice) use (&$link) {
        $link = $notice->actionUrl;

        return str_contains($notice->subject, 'Confirme seu e-mail');
    });

    $this->get(str_replace('/cadastro/confirmar/', '/cadastro/confirmar/x', $link))->assertForbidden(); // link adulterado
    $this->get($link)->assertOk()->assertSee('E-mail confirmado');

    expect($tenant->refresh()->email_verified_at)->not->toBeNull();
    $this->get(tenantUrl($tenant, '/cardapio'))->assertOk();
});

it('o dono reenvia o e-mail e o admin pode confirmar manualmente', function () {
    Notification::fake();
    $tenant = signUp();
    $owner = inTenant($tenant, fn () => \App\Modules\Access\Models\User::sole());

    $this->actingAs($owner)->post(tenantUrl($tenant, '/confirmar-email/reenviar'))->assertRedirect();
    Notification::assertSentOnDemandTimes(TenantNotice::class, 2);

    $this->actingAs(createPlatformUser(), 'platform');
    Livewire::test(Show::class, ['tenant' => $tenant])->assertSee('ainda não confirmou')->call('markEmailVerified');

    expect($tenant->refresh()->email_verified_at)->not->toBeNull();
});

it('estabelecimentos criados pela equipe já entram confirmados', function () {
    expect(provisionTenant('conf')->refresh()->email_verified_at)->not->toBeNull();
});
