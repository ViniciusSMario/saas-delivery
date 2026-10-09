<?php

// App do estabelecimento: empresa1.meusistema.com ou domínio próprio.
// O grupo "tenant" já identificou e ativou o tenant antes destas rotas.

use Illuminate\Support\Facades\Route;

Route::view('/', 'tenant.home')->name('tenant.home');
