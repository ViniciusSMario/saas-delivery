<?php

// App do estabelecimento: empresa1.meusistema.com ou domínio próprio.
// O grupo "tenant" já identificou e ativou o tenant antes destas rotas.

use App\Http\Controllers\Tenant\EstablishmentLogoController;
use App\Http\Controllers\Tenant\LogoutController;
use App\Livewire\Tenant\AuditLogIndex;
use App\Livewire\Tenant\Auth\ForgotPassword;
use App\Livewire\Tenant\Auth\Login;
use App\Livewire\Tenant\Auth\ResetPassword;
use App\Livewire\Tenant\Dashboard;
use App\Livewire\Tenant\Establishment\EditEstablishment;
use App\Livewire\Tenant\Establishment\EditSettings;
use App\Livewire\Tenant\Profile;
use App\Livewire\Tenant\Roles;
use App\Livewire\Tenant\Users;
use App\Modules\Access\Http\EnsureUserIsActive;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/esqueci-minha-senha', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

// Público: usado pelo cardápio.
Route::get('/logo', EstablishmentLogoController::class)->name('establishment.logo');

Route::middleware(['auth', AuthenticateSession::class, EnsureUserIsActive::class])->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::post('/logout', LogoutController::class)->name('logout');
    Route::get('/minha-conta', Profile::class)->name('profile');

    Route::get('/estabelecimento', EditEstablishment::class)->middleware('can:establishment.manage')->name('establishment.edit');
    Route::get('/configuracoes', EditSettings::class)->middleware('can:settings.manage')->name('settings.edit');

    Route::get('/usuarios', Users\Index::class)->middleware('can:users.view')->name('users.index');
    Route::get('/usuarios/novo', Users\Form::class)->middleware('can:users.manage')->name('users.create');
    Route::get('/usuarios/{user}', Users\Form::class)->middleware('can:users.manage')->name('users.edit');

    Route::get('/perfis', Roles\Index::class)->middleware('can:roles.manage')->name('roles.index');
    Route::get('/perfis/novo', Roles\Form::class)->middleware('can:roles.manage')->name('roles.create');
    Route::get('/perfis/{role}', Roles\Form::class)->middleware('can:roles.manage')->name('roles.edit');

    Route::get('/auditoria', AuditLogIndex::class)->middleware('can:audit.view')->name('audit.index');
});
