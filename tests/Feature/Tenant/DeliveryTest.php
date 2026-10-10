<?php

use App\Livewire\Tenant\Delivery\Board as DeliveryBoard;
use App\Livewire\Tenant\Delivery\Zones;
use App\Modules\Delivery\Models\DeliveryZone;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 15:00', 'America/Sao_Paulo'));
    $this->tenant = provisionTenant('dlv');
    inTenant($this->tenant, fn () => OpeningHour::query()->update(['is_closed' => false, 'opens_at' => '00:00', 'closes_at' => '23:59']));
    $this->menu = orderMenu($this->tenant);
});

function deliveryInput(array $menu, string $district, array $overrides = []): array
{
    return onlineOrderInput($menu, $overrides + [
        'fulfillment' => 'delivery',
        'address' => ['street' => 'Rua A', 'number' => '10', 'district' => $district, 'city' => 'Campinas'],
    ]);
}

function useZones(): void
{
    DeliveryZone::create(['name' => 'Centro', 'fee_cents' => Money::parse('5')]);
    DeliveryZone::create(['name' => 'São José', 'fee_cents' => Money::parse('9'), 'min_order_cents' => Money::parse('500'), 'eta_minutes' => 50]);
    DeliveryZone::create(['name' => 'Longe', 'fee_cents' => Money::parse('20'), 'is_active' => false]);
    Setting::write('delivery_fee_mode', 'zones');
}

/** Pedido de entrega já pronto na cozinha. */
function readyDelivery($test, string $district = 'Centro'): Order
{
    $order = placeOnline($test->tenant, deliveryInput($test->menu, $district));

    return inTenant($test->tenant, function () use ($order) {
        app(ChangeOrderStatus::class)->handle($order, OrderStatus::Preparing);
        app(ChangeOrderStatus::class)->handle($order, OrderStatus::Ready);

        return $order->refresh();
    });
}

it('cobra a taxa do bairro, aceita grafias diferentes e recusa bairro não atendido', function () {
    inTenant($this->tenant, fn () => useZones());

    $order = placeOnline($this->tenant, deliveryInput($this->menu, '  centro '));
    expect($order->delivery_fee_cents->format())->toBe('R$ 5,00')
        ->and($order->address_district)->toBe('Centro')
        ->and($order->delivery_zone_id)->not->toBeNull();

    // Sem acento e com pedido mínimo do bairro (R$ 500).
    expect(fn () => placeOnline($this->tenant, deliveryInput($this->menu, 'sao jose')))->toThrow(ValidationException::class, 'mínimo é de R$ 500,00');

    expect(fn () => placeOnline($this->tenant, deliveryInput($this->menu, 'Bairro Inexistente')))->toThrow(ValidationException::class, 'Ainda não entregamos');
    expect(fn () => placeOnline($this->tenant, deliveryInput($this->menu, 'Longe')))->toThrow(ValidationException::class, 'Ainda não entregamos');

    // Retirada não depende de bairro.
    expect(placeOnline($this->tenant, onlineOrderInput($this->menu))->delivery_fee_cents->isZero())->toBeTrue();
});

it('mantém a taxa única quando a cobrança por bairro está desligada', function () {
    inTenant($this->tenant, function () {
        DeliveryZone::create(['name' => 'Centro', 'fee_cents' => Money::parse('5')]);
        Setting::write('delivery_fee_cents', 700);
    });

    $order = placeOnline($this->tenant, deliveryInput($this->menu, 'Qualquer Bairro'));
    expect($order->delivery_fee_cents->format())->toBe('R$ 7,00')->and($order->delivery_zone_id)->toBeNull();
});

it('mostra os bairros no cardápio e no checkout', function () {
    inTenant($this->tenant, fn () => useZones());

    $this->get(tenantUrl($this->tenant, '/cardapio'))->assertOk()->assertSee('Entrega a partir de R$ 5,00')->assertSee('São José')->assertDontSee('Longe');
    $this->get(tenantUrl($this->tenant, '/cardapio/pedido'))->assertOk()->assertSee('Escolha o bairro')->assertSee('Centro · R$ 5,00');
});

it('o entregador pega o pedido pronto, sai e conclui a entrega', function () {
    $order = readyDelivery($this);
    $courier = createTenantUser($this->tenant, ['entregador']);
    $other = createTenantUser($this->tenant, ['entregador']);

    activateTenant($this->tenant);
    $this->actingAs($courier);
    Livewire::test(DeliveryBoard::class)->assertSet('tab', 'ready')->assertSee('#'.$order->number)->call('take', $order->id)->assertHasNoErrors()->assertSee('está com você');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Dispatched)
        ->and($order->courier_id)->toBe($courier->id)
        ->and($order->dispatched_at)->not->toBeNull();

    // Outro entregador não leva o mesmo pedido nem conclui a entrega dele.
    $this->actingAs($other);
    Livewire::test(DeliveryBoard::class)->call('take', $order->id)->assertHasErrors('status');
    Livewire::test(DeliveryBoard::class)->call('complete', $order->id)->assertHasErrors('status');

    $this->actingAs($courier);
    // Com entrega em andamento, a tela abre na aba "Comigo"; ao concluir, volta para "Para pegar".
    Livewire::test(DeliveryBoard::class)->assertSet('tab', 'mine')->assertSee('Abrir rota no mapa')->assertSee('Hoje você entregou 0 pedidos')
        ->call('complete', $order->id)->assertHasNoErrors()
        ->assertSet('tab', 'ready')->assertSee('Hoje você entregou 1 pedido');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Completed)->and($order->isPaid())->toBeTrue();
});

it('a gerência escolhe e troca o entregador e vê o fechamento do dia', function () {
    $first = readyDelivery($this);
    $second = readyDelivery($this);
    $manager = createTenantUser($this->tenant, ['gerente']);
    $ana = createTenantUser($this->tenant, ['entregador'], ['name' => 'Ana Entregadora']);
    $bia = createTenantUser($this->tenant, ['entregador'], ['name' => 'Bia Entregadora']);
    $cashier = createTenantUser($this->tenant, ['caixa']);

    activateTenant($this->tenant);
    $this->actingAs($manager);
    Livewire::test(DeliveryBoard::class)
        ->call('assign', $first->id, $ana->id)
        ->call('assign', $second->id, $ana->id)
        ->call('assign', $second->id, $bia->id)
        ->assertHasNoErrors()
        ->call('assign', $first->id, $cashier->id)->assertHasErrors('courier');

    expect($first->refresh()->courier_id)->toBe($ana->id)->and($second->refresh()->courier_id)->toBe($bia->id);

    // Ana com duas paradas: a rota leva as duas.
    $third = readyDelivery($this, 'Vila Nova');
    Livewire::test(DeliveryBoard::class)->call('assign', $third->id, $ana->id);
    $this->actingAs($ana);
    $html = Livewire::test(DeliveryBoard::class)->assertSee('Rota com as 2 paradas')->html();
    expect($html)->toContain('waypoints=');

    Livewire::test(DeliveryBoard::class)->call('complete', $first->id);
    $this->actingAs($manager);
    Livewire::test(DeliveryBoard::class)->assertSee('Entregas concluídas hoje')->assertSee('Ana Entregadora');
});

it('só quem faz entregas acessa a tela', function () {
    $this->actingAs(createTenantUser($this->tenant, ['entregador']))->get(tenantUrl($this->tenant, '/entregas'))->assertOk()->assertSee('Prontos para sair');
    $this->flushSession();
    $this->actingAs(createTenantUser($this->tenant, ['caixa']))->get(tenantUrl($this->tenant, '/entregas'))->assertForbidden();
});

it('cadastra bairros e liga a cobrança por bairro', function () {
    activateTenant($this->tenant);
    $this->actingAs(createTenantUser($this->tenant, ['dono']));

    Livewire::test(Zones::class)
        ->set('mode', 'zones')->assertHasErrors('mode')->assertSet('mode', 'fixed')
        ->set('name', 'Centro')->set('fee', '6,50')->set('eta', '30')->call('save')->assertHasNoErrors()
        ->set('mode', 'zones')->assertHasNoErrors();

    expect(Setting::read('delivery_fee_mode'))->toBe('zones')
        ->and(DeliveryZone::sole()->fee_cents->format())->toBe('R$ 6,50');

    Livewire::test(Zones::class)->call('delete', DeliveryZone::sole()->id)->assertSet('mode', 'fixed');
    expect(Setting::read('delivery_fee_mode'))->toBe('fixed');
});
