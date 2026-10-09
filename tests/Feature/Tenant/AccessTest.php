<?php

use App\Livewire\Tenant\Roles\Form as RoleForm;
use App\Livewire\Tenant\Users\Form as UserForm;
use App\Modules\Access\Actions\SaveRole;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\User;
use App\Modules\Access\Notifications\SetPasswordNotification;
use App\Modules\Access\Permission;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->a = tenantBySlug('teste1');
    $this->b = tenantBySlug('teste2');
});

it('libera ou bloqueia telas conforme as permissões do perfil', function (string $role, array $allowed, array $denied) {
    $user = createTenantUser($this->a, [$role]);

    foreach ($allowed as $path) {
        $this->actingAs($user)->get(tenantUrl($this->a, $path))->assertOk();
    }

    foreach ($denied as $path) {
        $this->actingAs($user)->get(tenantUrl($this->a, $path))->assertForbidden();
    }
})->with([
    'dono' => ['dono', ['/', '/estabelecimento', '/configuracoes', '/usuarios', '/perfis', '/auditoria'], []],
    'gerente' => ['gerente', ['/', '/estabelecimento', '/usuarios', '/auditoria'], ['/perfis']],
    'caixa' => ['caixa', ['/', '/minha-conta'], ['/estabelecimento', '/configuracoes', '/usuarios', '/perfis', '/auditoria']],
]);

it('derruba na hora o usuário desativado durante a sessão', function () {
    $user = createTenantUser($this->a, ['gerente']);
    inTenant($this->a, fn () => User::whereKey($user->id)->update(['is_active' => false]));

    $user->is_active = false;

    $this->actingAs($user)->get(tenantUrl($this->a, '/'))->assertRedirect(route('login'));
});

it('cria usuário, envia convite no domínio do tenant e audita', function () {
    Notification::fake();
    $owner = createTenantUser($this->a);
    activateTenant($this->a);
    $caixa = Role::where('slug', 'caixa')->first();

    Livewire::actingAs($owner)->test(UserForm::class)
        ->set('name', 'Maria Caixa')
        ->set('email', 'Maria@Exemplo.com')
        ->set('roleIds', [$caixa->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('users.index'));

    $maria = User::where('email', 'maria@exemplo.com')->firstOrFail();

    expect($maria->password)->toBeNull()
        ->and($maria->roles->pluck('slug')->all())->toBe(['caixa'])
        ->and($maria->hasPermission(Permission::PosUse))->toBeTrue()
        ->and($maria->hasPermission(Permission::UsersManage))->toBeFalse()
        ->and(AuditLog::where('action', 'user.created')->where('subject_id', $maria->id)->value('user_id'))->toBe($owner->id);

    Notification::assertSentTo($maria, SetPasswordNotification::class, fn ($n) => $n->invitation
        && str_starts_with($n->url($maria), 'http://teste1.localhost'));

    // Não aparece no outro estabelecimento.
    // (a sessão de teste é compartilhada; a mensagem flash com o e-mail não pode ir junto)
    app(App\Tenancy\TenantContext::class)->reset();
    $this->flushSession();
    $this->actingAs(createTenantUser($this->b))->get(tenantUrl($this->b, '/usuarios'))->assertDontSee('maria@exemplo.com');
});

it('não deixa repetir e-mail no mesmo estabelecimento', function () {
    $owner = createTenantUser($this->a);
    createTenantUser($this->a, ['caixa'], ['email' => 'duplicado@email.com']);
    activateTenant($this->a);

    Livewire::actingAs($owner)->test(UserForm::class)
        ->set('name', 'Outro')->set('email', 'duplicado@email.com')
        ->call('save')->assertHasErrors(['email' => 'unique']);
});

it('impede gerente de conceder o perfil de dono ou de editar um dono', function () {
    $gerente = createTenantUser($this->a, ['gerente']);
    $owner = createTenantUser($this->a);
    $caixa = createTenantUser($this->a, ['caixa']);
    activateTenant($this->a);
    $donoRole = Role::where('slug', 'dono')->first();

    Livewire::actingAs($gerente)->test(UserForm::class, ['user' => User::find($caixa->id)])
        ->set('roleIds', [$donoRole->id])
        ->call('save')->assertHasErrors(['roleIds']);

    Livewire::actingAs($gerente)->test(UserForm::class, ['user' => User::find($owner->id)])
        ->set('name', 'Novo nome')
        ->call('save')->assertHasErrors(['email']);

    expect(User::find($caixa->id)->roles->pluck('slug')->all())->toBe(['caixa']);
});

it('mantém pelo menos um dono ativo', function () {
    activateTenant(provisionTenant('own'));
    $owner = createTenantUser(tenant());

    Livewire::actingAs($owner)->test(UserForm::class, ['user' => User::find($owner->id)])
        ->set('roleIds', [Role::where('slug', 'gerente')->value('id')])
        ->call('save')->assertHasErrors(['roleIds']);

    expect(User::find($owner->id)->isOwner())->toBeTrue();
});

it('cria perfil personalizado e o usuário passa a ter as permissões dele', function () {
    $owner = createTenantUser($this->a);
    activateTenant($this->a);

    Livewire::actingAs($owner)->test(RoleForm::class)
        ->set('name', 'Supervisor de salão')
        ->set('permissions', [Permission::UsersView->value, Permission::TablesManage->value])
        ->call('save')
        ->assertHasNoErrors();

    $role = Role::where('name', 'Supervisor de salão')->firstOrFail();
    $user = createTenantUser($this->a, [$role->slug]);

    expect($role->is_system)->toBeFalse()
        ->and($user->hasPermission(Permission::UsersView))->toBeTrue()
        ->and($user->hasPermission(Permission::UsersManage))->toBeFalse();

    $this->actingAs($user)->get(tenantUrl($this->a, '/usuarios'))->assertOk();
    $this->actingAs($user)->get(tenantUrl($this->a, '/usuarios/novo'))->assertForbidden();
});

it('não deixa conceder permissão que quem edita não tem, nem mexer no perfil de dono', function () {
    $gerente = createTenantUser($this->a, ['gerente']);
    activateTenant($this->a);

    expect(fn () => app(SaveRole::class)->handle(null, ['name' => 'Escalada'], [Permission::RolesManage->value], $gerente))
        ->toThrow(ValidationException::class);

    expect(fn () => app(SaveRole::class)->handle(Role::where('slug', 'dono')->first(), ['name' => 'Dono'], [], $gerente))
        ->toThrow(ValidationException::class);
});

it('não exclui perfil do sistema nem perfil em uso', function () {
    $owner = createTenantUser($this->a);
    activateTenant($this->a);
    $save = app(SaveRole::class);

    expect(fn () => $save->delete(Role::where('slug', 'caixa')->first(), $owner))->toThrow(ValidationException::class);

    $custom = $save->handle(null, ['name' => 'Temporário '.uniqid()], [], $owner);
    createTenantUser($this->a, [$custom->slug]);
    expect(fn () => $save->delete($custom, $owner))->toThrow(ValidationException::class);
});

it('não encontra pelo id um usuário de outro estabelecimento', function () {
    $ownerB = createTenantUser($this->b);
    $onlyInA = createTenantUser($this->a);
    $missingInB = inTenant($this->b, fn () => User::max('id')) + 1000;

    $this->actingAs($ownerB)->get(tenantUrl($this->b, '/usuarios/'.$missingInB))->assertNotFound();
    expect(inTenant($this->b, fn () => User::where('email', $onlyInA->email)->exists()))->toBeFalse();
});
