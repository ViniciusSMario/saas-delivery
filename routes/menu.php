<?php

// Cardápio público: empresa1.meusistema.com/cardapio ou meusistema.com/cardapio/{slug}.
// O slug é consumido pelo PathResolver; as rotas não o recebem.
// Este arquivo é registrado nos dois formatos, por isso as rotas não têm nome:
// a view monta os links a partir da URL atual.

use App\Http\Controllers\Tenant\EstablishmentCoverController;
use App\Http\Controllers\Tenant\EstablishmentLogoController;
use App\Http\Controllers\Tenant\OrderPushController;
use App\Http\Controllers\Tenant\PublicMenuController;
use App\Http\Controllers\Tenant\PublicOrderController;
use App\Tenancy\Middleware\EnsureTenantVerified;
use Illuminate\Support\Facades\Route;

// Cadastro self-service: o cardápio só vai ao ar depois da confirmação do e-mail do dono.
Route::middleware(EnsureTenantVerified::class)->group(function () {
    Route::get('/', [PublicMenuController::class, 'index']);
    Route::get('/manifest.webmanifest', [PublicMenuController::class, 'manifest']);
    Route::get('/logo', EstablishmentLogoController::class);
    Route::get('/capa', EstablishmentCoverController::class);
    Route::get('/imagens/{image}', [PublicMenuController::class, 'productImage'])->whereNumber('image');
    Route::get('/combos/{combo}/imagem', [PublicMenuController::class, 'comboImage'])->whereNumber('combo');

    // Pedido online
    Route::get('/pedido', [PublicOrderController::class, 'checkout']);
    Route::post('/pedido', [PublicOrderController::class, 'store']);
    Route::get('/pedido/{token}', [PublicOrderController::class, 'show']);
    Route::get('/pedido/{token}/status', [PublicOrderController::class, 'status']);

    // Avisos do andamento no celular do cliente (Web Push).
    Route::post('/pedido/{token}/avisos', [OrderPushController::class, 'store'])->middleware('throttle:20,1');
    Route::delete('/pedido/{token}/avisos', [OrderPushController::class, 'destroy'])->middleware('throttle:20,1');
});
