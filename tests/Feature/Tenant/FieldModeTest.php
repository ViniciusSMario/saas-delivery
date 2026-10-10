<?php

use App\Livewire\Tenant\Delivery\Board as DeliveryBoard;
use App\Livewire\Tenant\FieldNav;
use App\Livewire\Tenant\Tables\CommandPage;
use App\Livewire\Tenant\Tables\Map;
use App\Livewire\Tenant\Tables\ReadyToServe;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Permission;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Tables\Actions\ManageCommand;
use App\Modules\Tables\ServeQueue;
use Livewire\Livewire;

it('garçom e entregador entram direto na tela deles, no modo app', function () {
    $tenant = provisionTenant('app');
    $garcom = createTenantUser($tenant, ['garcom']);
    $entregador = createTenantUser($tenant, ['entregador']);
    $dono = createTenantUser($tenant, ['dono']);

    $this->actingAs($garcom)->get(tenantUrl($tenant, '/'))->assertRedirect(tenantUrl($tenant, '/mesas'));
    $this->get(tenantUrl($tenant, '/mesas'))->assertOk()
        ->assertSee('aria-label="Navegação"', false)
        ->assertSee('Prontos')
        ->assertDontSee('Painel do estabelecimento')
        ->assertDontSee('Produtos');

    $this->flushSession();
    $this->actingAs($entregador)->get(tenantUrl($tenant, '/'))->assertRedirect(tenantUrl($tenant, '/entregas'));
    $this->get(tenantUrl($tenant, '/entregas'))->assertOk()->assertSee('Para pegar')->assertDontSee('Painel do estabelecimento');

    // Dono e gerência continuam no painel com a barra lateral.
    $this->flushSession();
    $this->actingAs($dono)->get(tenantUrl($tenant, '/'))->assertOk()->assertSee('Painel do estabelecimento');
    $this->get(tenantUrl($tenant, '/mesas'))->assertOk()->assertDontSee('aria-label="Navegação"', false);
});

it('só usa o modo app quem não tem nada além de mesas e entregas', function () {
    $tenant = provisionTenant('app');

    inTenant($tenant, function () use ($tenant) {
        expect(createTenantUser($tenant, ['garcom'])->usesFieldMode())->toBeTrue()
            ->and(createTenantUser($tenant, ['entregador'])->usesFieldMode())->toBeTrue()
            ->and(createTenantUser($tenant, ['cozinha'])->usesFieldMode())->toBeFalse()
            ->and(createTenantUser($tenant, ['caixa'])->usesFieldMode())->toBeFalse()
            ->and(createTenantUser($tenant, ['gerente'])->usesFieldMode())->toBeFalse();

        // Garçom que também opera o caixa precisa do painel completo.
        $role = Role::create(['slug' => 'garcom-caixa', 'name' => 'Garçom e caixa']);
        $role->syncPermissions([Permission::TablesManage, Permission::OrdersView, Permission::CashOperate]);
        $user = createTenantUser($tenant, ['garcom-caixa']);
        expect($user->usesFieldMode())->toBeFalse();
    });
});

it('garçom vê os pedidos de mesa prontos e marca como servido', function () {
    ['tenant' => $tenant, 'burger' => $burger, 'soda' => $soda, 't1' => $t1] = salon();
    $garcom = userIn($tenant, ['garcom']);
    $manage = app(ManageCommand::class);
    $command = $manage->open($t1, null, 2, $garcom);
    $ready = $manage->addItems($command, [line($burger, 2)], $garcom);
    $cooking = $manage->addItems($command, [line($soda)], $garcom);

    app(ChangeOrderStatus::class)->handle($ready, OrderStatus::Preparing);
    app(ChangeOrderStatus::class)->handle($ready, OrderStatus::Ready);

    expect(ServeQueue::count())->toBe(1);
    Livewire::actingAs($garcom)->test(FieldNav::class)->assertSet('toServe', 1)->assertSee('Prontos');
    Livewire::actingAs($garcom)->test(Map::class)->assertSee('1 pedido pronto para servir');

    Livewire::actingAs($garcom)->test(ReadyToServe::class)
        ->assertSee('Mesa 1')
        ->assertSee('Hambúrguer')
        ->assertDontSee('Refrigerante')
        ->call('serve', $ready->id)
        ->assertHasNoErrors()
        ->assertDispatched('field-refresh')
        ->assertSee('Nada para servir agora');

    expect($ready->refresh()->status)->toBe(OrderStatus::Completed)
        ->and($ready->history()->get()->last()->note)->toBe('Servido na mesa');

    // Pedido ainda na cozinha não pode ser "servido".
    expect(fn () => Livewire::actingAs($garcom)->test(ReadyToServe::class)->call('serve', $cooking->id))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);

    // A comanda mostra o pedido como servido e continua aberta para a conta.
    Livewire::actingAs($garcom)->test(CommandPage::class, ['command' => $command])->assertSee('Servido')->assertSee('Lançar itens');
});

it('avisa o celular quando chega pedido pronto', function () {
    ['tenant' => $tenant, 'burger' => $burger, 't1' => $t1] = salon();
    $garcom = userIn($tenant, ['garcom']);
    $manage = app(ManageCommand::class);
    $order = $manage->addItems($manage->open($t1, null, 2, $garcom), [line($burger)], $garcom);

    $nav = Livewire::actingAs($garcom)->test(FieldNav::class)->assertSet('toServe', 0);
    $nav->call('refresh')->assertNotDispatched('field-alert');

    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Preparing);
    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Ready);

    $nav->call('refresh')->assertSet('toServe', 1)->assertDispatched('field-alert');
});

it('mesa abre com o número de pessoas e o filtro mostra só as ocupadas', function () {
    ['tenant' => $tenant, 't1' => $t1] = salon();
    $garcom = userIn($tenant, ['garcom']);

    Livewire::actingAs($garcom)->test(Map::class)
        ->call('startOpen', $t1->id)->assertSet('people', 2)
        ->set('people', 150)->call('open')->assertHasNoErrors();

    expect($t1->openCommand()->first()->people)->toBe(99);

    Livewire::actingAs($garcom)->test(Map::class)
        ->call('setFilter', 'busy')->assertSee('Ocupadas')->assertViewHas('areas', fn ($areas) => $areas->flatten()->pluck('name')->all() === ['1'])
        ->call('setFilter', 'free')->assertViewHas('areas', fn ($areas) => $areas->flatten()->pluck('name')->all() === ['2']);
});
