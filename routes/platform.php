<?php

// Admin da plataforma: admin.meusistema.com. Guard "platform", schema public, sem tenant.

use App\Http\Controllers\Platform\BackupDownloadController;
use App\Http\Controllers\Platform\LogoutController;
use App\Livewire\Platform\Auth\Login;
use App\Livewire\Platform\Auth\TwoFactor;
use App\Livewire\Platform\Tenants;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:platform')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/login/2fa', TwoFactor::class)->name('two-factor');
});

Route::middleware('auth:platform')->group(function () {
    Route::get('/', Tenants\Index::class)->name('tenants.index');
    Route::get('/tenants/novo', Tenants\Create::class)->middleware('can:platform.manage-tenants')->name('tenants.create');
    Route::get('/tenants/{tenant}', Tenants\Show::class)->withTrashed()->name('tenants.show');
    Route::get('/backups/{backup}/download', BackupDownloadController::class)->name('backups.download');
    Route::get('/assinaturas', \App\Livewire\Platform\Billing\Index::class)->name('billing.index');
    Route::post('/logout', LogoutController::class)->name('logout');
});
