<?php

// Site central (meusistema.com): apresentação, planos, cadastro self-service e cobrança.

use App\Http\Controllers\Central\BillingController;
use App\Http\Controllers\Central\SignupController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', [SignupController::class, 'home'])->name('home');
Route::get('/cadastro', [SignupController::class, 'create'])->name('signup.create');
Route::post('/cadastro', [SignupController::class, 'store'])->name('signup.store');
Route::get('/cadastro/disponivel', [SignupController::class, 'check'])->middleware('throttle:60,1')->name('signup.check');
Route::get('/cadastro/pronto', [SignupController::class, 'done'])->name('signup.done');

// Webhook do gateway (Asaas): autenticado pelo token do gateway, sem CSRF.
Route::post('/webhooks/billing/{gateway}', [BillingController::class, 'webhook'])
    ->withoutMiddleware(ValidateCsrfToken::class)
    ->name('billing.webhook');

// Fatura do gateway de teste (BILLING_DRIVER=fake).
Route::middleware('signed')->group(function () {
    Route::get('/faturas/teste/{charge}', [BillingController::class, 'fakeInvoice'])->name('billing.fake.show');
    Route::post('/faturas/teste/{charge}', [BillingController::class, 'fakePay'])->name('billing.fake.pay');
});
