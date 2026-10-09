<?php

use App\Modules\Access\Models\User;
use App\Modules\Access\Notifications\SetPasswordNotification;
use App\Platform\Models\PlatformUser;
use Illuminate\Support\Facades\Notification;

it('tenants:create cria o dono e envia o convite', function () {
    Notification::fake();

    $this->artisan('tenants:create', [
        'slug' => 'cmd-dono',
        'name' => 'Restaurante Comando',
        '--owner-email' => 'dono@comando.com',
        '--owner-name' => 'Dono Comando',
    ])->assertSuccessful();

    $tenant = tenantBySlug('cmd-dono');
    $owner = inTenant($tenant, fn () => User::with('roles')->where('email', 'dono@comando.com')->firstOrFail());

    expect($owner->isOwner())->toBeTrue();
    Notification::assertSentTo($owner, SetPasswordNotification::class);
});

it('tenants:invite-user cria usuário com o perfil pedido', function () {
    Notification::fake();
    $tenant = tenantBySlug('teste2');

    $this->artisan('tenants:invite-user', ['tenant' => 'teste2', 'email' => 'garcom@teste2.com', 'name' => 'Garçom', '--role' => 'garcom'])
        ->assertSuccessful();

    $user = inTenant($tenant, fn () => User::with('roles')->where('email', 'garcom@teste2.com')->firstOrFail());
    expect($user->roles->pluck('slug')->all())->toBe(['garcom']);

    $this->artisan('tenants:invite-user', ['tenant' => 'teste2', 'email' => 'garcom@teste2.com', 'name' => 'De novo'])
        ->assertFailed();
});

it('platform:create-user cria usuário da equipe sem 2FA configurado', function () {
    $this->artisan('platform:create-user', ['email' => 'Equipe@SaaS.test', 'name' => 'Equipe', '--no-interaction' => true])
        ->assertSuccessful();

    $user = PlatformUser::where('email', 'equipe@saas.test')->firstOrFail();
    expect($user->isAdmin())->toBeTrue()->and($user->hasTwoFactor())->toBeFalse();
});
