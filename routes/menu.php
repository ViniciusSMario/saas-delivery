<?php

// Cardápio público: empresa1.meusistema.com/cardapio ou meusistema.com/cardapio/{slug}.
// O slug é consumido pelo PathResolver; as rotas não o recebem.

use Illuminate\Support\Facades\Route;

Route::view('/', 'tenant.menu');
