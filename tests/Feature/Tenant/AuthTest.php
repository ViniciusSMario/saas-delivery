<?php

use App\Livewire\Tenant\Auth\ForgotPassword;
use App\Livewire\Tenant\Auth\Login;
use App\Livewire\Tenant\Auth\ResetPassword;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Access\Models\User;
use App\Modules\Access\Notifications\SetPasswordNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

beforeEach(function () {
    $this->a = tenantBySlug('teste1');
    $this->b = tenantBySlug('teste2');
});

it('mostra o login no endereço do estabelecimento e manda visitante para lá', function () {
    $this->get(tenantUrl($this->a, '/login'))->assertOk()->assertSee('Tenant Teste 1')->assertSee('Entrar');
    $this->get(tenantUrl($this->a, '/'))->assertRedirect(tenantUrl($this->a, '/login'));
});

it('entra com e-mail e senha e registra na auditoria', function () {
    $user = createTenantUser($this->a);
    activateTenant($this->a);

    Livewire::test(Login::class)
        ->set('email', mb_strtoupper($user->email))
        ->set('password', TEST_PASSWORD)
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect(Auth::guard('web')->id())->toBe($user->id)
        ->and($user->fresh()->last_login_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'auth.login')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('recusa senha errada e limita tentativas', function () {
    $user = createTenantUser($this->a);
    activateTenant($this->a);

    $component = Livewire::test(Login::class)->set('email', $user->email)->set('password', 'errada-123');

    foreach (range(1, Login::MAX_ATTEMPTS) as $attempt) {
        $component->call('login')->assertHasErrors(['email']);
    }

    $component->set('password', TEST_PASSWORD)->call('login')->assertHasErrors(['email']);
    expect(Auth::guard('web')->check())->toBeFalse();
});

it('não deixa usuário desativado entrar', function () {
    $user = createTenantUser($this->a, attributes: ['is_active' => false]);
    activateTenant($this->a);

    Livewire::test(Login::class)->set('email', $user->email)->set('password', TEST_PASSWORD)
        ->call('login')->assertHasErrors(['email']);
});

it('trata o mesmo e-mail em dois estabelecimentos como contas independentes', function () {
    createTenantUser($this->a, attributes: ['email' => 'mesmo@email.com', 'password' => 'senha-de-a-123']);
    createTenantUser($this->b, attributes: ['email' => 'mesmo@email.com', 'password' => 'senha-de-b-123']);

    activateTenant($this->b);

    Livewire::test(Login::class)->set('email', 'mesmo@email.com')->set('password', 'senha-de-a-123')
        ->call('login')->assertHasErrors(['email']);

    Livewire::test(Login::class)->set('email', 'mesmo@email.com')->set('password', 'senha-de-b-123')
        ->call('login')->assertHasNoErrors();
});

it('não aceita em B a sessão autenticada de A, mesmo com o mesmo id de usuário', function () {
    $userA = createTenantUser($this->a);
    // Garante que existe em B um usuário com o mesmo id.
    inTenant($this->b, function () use ($userA) {
        while ((User::max('id') ?? 0) < $userA->id) {
            createTenantUser(tenantBySlug('teste2'));
        }
    });

    $this->withSession([
        'tenant_id' => $this->a->id,
        Auth::guard('web')->getName() => $userA->id,
    ])->get(tenantUrl($this->b, '/'))->assertRedirect(tenantUrl($this->b, '/login'));
});

it('envia o link de "esqueci minha senha" no domínio do estabelecimento', function () {
    Notification::fake();
    $user = createTenantUser($this->a);
    activateTenant($this->a);

    Livewire::test(ForgotPassword::class)->set('email', $user->email)->call('send')->assertSet('sent', true);
    Livewire::test(ForgotPassword::class)->set('email', 'naoexiste@x.com')->call('send')->assertSet('sent', true);

    Notification::assertSentTo($user, SetPasswordNotification::class, function (SetPasswordNotification $n) use ($user) {
        return ! $n->invitation && str_starts_with($n->url($user), 'http://teste1.localhost');
    });
    Notification::assertCount(1);
});

it('define nova senha pelo link e depois entra com ela', function () {
    $user = createTenantUser($this->a);
    activateTenant($this->a);
    $token = Password::broker('users')->createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'nova-senha-456')
        ->set('password_confirmation', 'nova-senha-456')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('nova-senha-456', $user->fresh()->password))->toBeTrue();

    // O token é de uso único.
    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)->set('password', 'outra-senha-789')->set('password_confirmation', 'outra-senha-789')
        ->call('save')->assertHasErrors(['email']);
});

it('não aceita em B o token de redefinição gerado em A', function () {
    $userA = createTenantUser($this->a, attributes: ['email' => 'token@email.com']);
    createTenantUser($this->b, attributes: ['email' => 'token@email.com']);
    $token = inTenant($this->a, fn () => Password::broker('users')->createToken($userA));

    activateTenant($this->b);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'token@email.com')->set('password', 'invasor-123')->set('password_confirmation', 'invasor-123')
        ->call('save')->assertHasErrors(['email']);
});

it('sai do sistema', function () {
    $user = createTenantUser($this->a);

    $this->actingAs($user)->post(tenantUrl($this->a, '/logout'))->assertRedirect(tenantUrl($this->a, '/login'));
    expect(Auth::guard('web')->check())->toBeFalse();
});
