<?php

// App do estabelecimento: empresa1.meusistema.com ou domínio próprio.
// O grupo "tenant" já identificou e ativou o tenant antes destas rotas.

use App\Http\Controllers\Tenant\EstablishmentLogoController;
use App\Http\Controllers\Tenant\LogoutController;
use App\Http\Controllers\Tenant\MenuImageController;
use App\Livewire\Tenant\AuditLogIndex;
use App\Livewire\Tenant\Auth\ForgotPassword;
use App\Livewire\Tenant\Auth\Login;
use App\Livewire\Tenant\Auth\ResetPassword;
use App\Livewire\Tenant\Dashboard;
use App\Livewire\Tenant\Establishment\EditEstablishment;
use App\Livewire\Tenant\Establishment\EditSettings;
use App\Livewire\Tenant\Menu;
use App\Livewire\Tenant\Orders;
use App\Livewire\Tenant\Tables;
use App\Livewire\Tenant\Cash;
use App\Livewire\Tenant\Stock;
use App\Livewire\Tenant\Customers;
use App\Livewire\Tenant\Finance;
use App\Livewire\Tenant\Reports;
use App\Http\Controllers\Tenant\ReportExportController;
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
    Route::get('/assinatura', \App\Livewire\Tenant\Subscription::class)->middleware('can:billing.manage')->name('subscription');

    Route::get('/pedidos', Orders\Board::class)->middleware('can:orders.view')->name('orders.board');
    Route::get('/pedidos/historico', Orders\Index::class)->middleware('can:orders.view')->name('orders.index');
    Route::get('/pedidos/{order}', Orders\Show::class)->whereNumber('order')->middleware('can:orders.view')->name('orders.show');
    Route::get('/balcao', Orders\Counter::class)->middleware('can:pos.use')->name('orders.counter');

    Route::get('/entregas', \App\Livewire\Tenant\Delivery\Board::class)->middleware('can:deliveries.operate')->name('delivery.board');
    Route::get('/entregas/bairros', \App\Livewire\Tenant\Delivery\Zones::class)->middleware('can:settings.manage')->name('delivery.zones');

    Route::get('/impressao',\App\Livewire\Tenant\Printing\Station::class)->middleware('can:orders.view')->name('printing.station');
    Route::get('/impressoras', \App\Livewire\Tenant\Printing\Settings::class)->middleware('can:settings.manage')->name('printing.settings');

    Route::get('/mesas', Tables\Map::class)->middleware('can:tables.manage')->name('tables.map');
    Route::get('/mesas/cadastro', Tables\Manage::class)->middleware('can:establishment.manage')->name('tables.manage');
    Route::get('/comandas/{command}', Tables\CommandPage::class)->whereNumber('command')->middleware('can:tables.manage')->name('tables.command');

    Route::middleware('can:stock.manage')->name('stock.')->group(function () {
        Route::get('/estoque', Stock\Index::class)->name('index');
        Route::get('/estoque/insumos/{item}', Stock\Show::class)->whereNumber('item')->name('show');
        Route::get('/estoque/fichas', Stock\Recipes::class)->name('recipes');
        Route::get('/estoque/fichas/produto/{product}', Stock\RecipeEditor::class)->whereNumber('product')->name('recipe.product');
        Route::get('/estoque/fichas/adicional/{addon}', Stock\RecipeEditor::class)->whereNumber('addon')->name('recipe.addon');
        Route::get('/estoque/consumo', Stock\Consumption::class)->name('consumption');
    });

    Route::get('/clientes', Customers\Index::class)->middleware('can:customers.manage')->name('customers.index');
    Route::get('/clientes/{customer}', Customers\Show::class)->whereNumber('customer')->middleware('can:customers.manage')->name('customers.show');

    Route::get('/financeiro', Finance\Index::class)->middleware('can:finance.view')->name('finance.index');
    Route::get('/financeiro/novo', Finance\EntryForm::class)->middleware('can:finance.manage')->name('finance.create');
    Route::get('/financeiro/{entry}', Finance\EntryForm::class)->whereNumber('entry')->middleware('can:finance.manage')->name('finance.edit');
    Route::get('/financeiro/contas', Finance\Accounts::class)->middleware('can:finance.manage')->name('finance.accounts');

    Route::get('/relatorios', Reports\Index::class)->middleware('can:reports.view')->name('reports.index');
    Route::get('/relatorios/exportar/{type}', ReportExportController::class)->whereIn('type', ['vendas', 'produtos'])->middleware('can:reports.view')->name('reports.export');

    Route::get('/caixa', Cash\Current::class)->middleware('can:cash.operate')->name('cash.current');
    Route::get('/caixa/sessoes', Cash\Sessions::class)->middleware('can:cash.manage')->name('cash.sessions');
    Route::get('/caixa/sessoes/{session}', Cash\Report::class)->whereNumber('session')->middleware('can:cash.operate')->name('cash.report');

    Route::middleware('can:menu.manage')->name('menu.')->group(function () {
        Route::get('/categorias', Menu\Categories::class)->name('categories');

        Route::get('/produtos', Menu\Products\Index::class)->name('products.index');
        Route::get('/produtos/novo', Menu\Products\Form::class)->name('products.create');
        Route::get('/produtos/{product}', Menu\Products\Form::class)->whereNumber('product')->name('products.edit');
        Route::get('/produtos/imagens/{image}', [MenuImageController::class, 'product'])->whereNumber('image')->name('images.show');

        Route::get('/adicionais', Menu\AddonGroups\Index::class)->name('addon-groups.index');
        Route::get('/adicionais/novo', Menu\AddonGroups\Form::class)->name('addon-groups.create');
        Route::get('/adicionais/{group}', Menu\AddonGroups\Form::class)->whereNumber('group')->name('addon-groups.edit');

        Route::get('/combos', Menu\Combos\Index::class)->name('combos.index');
        Route::get('/combos/novo', Menu\Combos\Form::class)->name('combos.create');
        Route::get('/combos/{combo}', Menu\Combos\Form::class)->whereNumber('combo')->name('combos.edit');
        Route::get('/combos/{combo}/imagem', [MenuImageController::class, 'combo'])->whereNumber('combo')->name('combos.image');
    });
});
