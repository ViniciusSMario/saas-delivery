<?php

use App\Livewire\Tenant\Cash\Current;
use App\Livewire\Tenant\Orders\Counter;
use App\Livewire\Tenant\Tables\CommandPage;
use App\Livewire\Tenant\Tables\Map;
use App\Modules\Access\Models\User;
use App\Modules\Cash\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\MovementType;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Tables\Actions\ManageCommand;
use App\Modules\Tables\CommandStatus;
use App\Modules\Tables\Models\Command;
use App\Modules\Tables\Models\DiningTable;
use App\Support\Money;
use App\Tenancy\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** Tenant novo por teste: caixa, mesas e sequências limpos. */
function salon(): array
{
    $tenant = provisionTenant('salao');
    activateTenant($tenant);

    $category = Category::create(['name' => 'Pratos']);
    $burger = Product::create(['category_id' => $category->id, 'name' => 'Hambúrguer', 'price_cents' => Money::parse('30')]);
    $soda = Product::create(['category_id' => $category->id, 'name' => 'Refrigerante', 'price_cents' => Money::parse('8')]);
    $t1 = DiningTable::create(['name' => '1', 'seats' => 4]);
    $t2 = DiningTable::create(['name' => '2', 'seats' => 2]);
    OpeningHour::query()->update(['is_closed' => false, 'opens_at' => '00:00', 'closes_at' => '23:59']);

    return compact('tenant', 'burger', 'soda', 't1', 't2');
}

function userIn(Tenant $tenant, array $roles): User
{
    return createTenantUser($tenant, $roles);
}

function openCash(User $user, string $opening = '0'): CashSession
{
    return app(CashLedger::class)->open(CashRegister::create(['name' => 'Caixa '.Str::random(6)]), $user, Money::parse($opening));
}

function line(Product $product, int $quantity = 1): array
{
    return ['type' => 'product', 'id' => $product->id, 'quantity' => $quantity];
}

it('abre caixa com fundo, faz sangria e suprimento e fecha com conferência', function () {
    ['tenant' => $tenant] = salon();
    $caixa = userIn($tenant, ['caixa']);
    $ledger = app(CashLedger::class);
    $session = openCash($caixa, '100');

    expect(fn () => $ledger->adjust($session, MovementType::Withdrawal, Money::parse('150'), $caixa, 'banco'))
        ->toThrow(ValidationException::class, 'maior que o dinheiro');
    expect(fn () => $ledger->adjust($session, MovementType::Withdrawal, Money::parse('10'), $caixa, ''))
        ->toThrow(ValidationException::class);

    $ledger->adjust($session, MovementType::Withdrawal, Money::parse('40'), $caixa, 'Depósito no banco');
    $ledger->adjust($session, MovementType::Deposit, Money::parse('15'), $caixa, 'Reforço de troco');

    expect($session->expectedByMethod()['cash']->cents)->toBe(7500);

    // Contou R$ 70 em dinheiro: falta de R$ 5.
    $closed = $ledger->close($session, $caixa, ['cash' => Money::parse('70')], 'conferido');

    expect($closed->status)->toBe('closed')
        ->and($closed->expected['cash'])->toBe(7500)
        ->and($closed->counted['cash'])->toBe(7000)
        ->and($closed->difference_cents->cents)->toBe(-500);

    expect(fn () => $ledger->adjust($closed, MovementType::Deposit, Money::parse('1'), $caixa, 'x'))->toThrow(ValidationException::class);
});

it('não abre o mesmo caixa duas vezes nem dois caixas para o mesmo operador', function () {
    ['tenant' => $tenant] = salon();
    $a = userIn($tenant, ['caixa']);
    $b = userIn($tenant, ['caixa']);
    $register = CashRegister::create(['name' => 'Único']);
    $ledger = app(CashLedger::class);

    $ledger->open($register, $a, Money::zero());

    expect(fn () => $ledger->open($register, $b, Money::zero()))->toThrow(ValidationException::class)
        ->and(fn () => $ledger->open(CashRegister::create(['name' => 'Outro']), $a, Money::zero()))->toThrow(ValidationException::class);
});

it('venda de balcão exige caixa aberto e entra no caixa do operador', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    $caixa = userIn($tenant, ['caixa']);
    $input = ['lines' => [line($burger, 2)], 'fulfillment' => 'counter', 'payment_method' => 'pix'];

    expect(fn () => app(PlaceOrder::class)->handle($input, OrderSource::Counter, $caixa))
        ->toThrow(ValidationException::class, 'Abra o caixa');

    $session = openCash($caixa);
    $order = app(PlaceOrder::class)->handle($input, OrderSource::Counter, $caixa);

    $movement = CashMovement::where('order_id', $order->id)->first();
    expect($movement->type)->toBe(MovementType::Sale)
        ->and($movement->amount_cents->cents)->toBe(6000)
        ->and($movement->cash_session_id)->toBe($session->id)
        ->and($order->payments()->value('cash_session_id'))->toBe($session->id)
        ->and($session->expectedByMethod()['pix']->cents)->toBe(6000);

    // Cancelar venda paga gera estorno no caixa.
    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Cancelled, $caixa, 'Cliente desistiu');
    expect($session->expectedByMethod()['pix']->cents)->toBe(0)
        ->and($order->payments()->first()->status)->toBe(PaymentStatus::Refunded);
});

it('entrega concluída entra no caixa aberto', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    $caixa = userIn($tenant, ['caixa']);
    $cozinha = userIn($tenant, ['cozinha']);
    $session = openCash($caixa);

    $order = app(PlaceOrder::class)->handle([
        'lines' => [line($burger)], 'fulfillment' => 'pickup', 'payment_method' => 'credit',
        'customer' => ['name' => 'Rita', 'phone' => '11977775555'],
    ], OrderSource::Online);

    foreach (range(1, 3) as $step) {
        app(ChangeOrderStatus::class)->handle($order->fresh(), $order->fresh()->status->next(Fulfillment::Pickup), $cozinha);
    }

    // A cozinha não tem caixa: vai para o caixa aberto mais recente.
    expect($order->fresh()->status)->toBe(OrderStatus::Completed)
        ->and($session->expectedByMethod()['credit']->cents)->toBe(3000);
});

it('abre comanda na mesa, lança itens para a cozinha e não deixa abrir duas na mesma mesa', function () {
    ['tenant' => $tenant, 'burger' => $burger, 'soda' => $soda, 't1' => $t1] = salon();
    $garcom = userIn($tenant, ['garcom']);
    $manage = app(ManageCommand::class);

    $command = $manage->open($t1, null, 3, $garcom);
    expect(fn () => $manage->open($t1, null, 2, $garcom))->toThrow(ValidationException::class, 'já tem uma comanda');

    $order = $manage->addItems($command, [line($burger, 2), line($soda, 3)], $garcom, 'sem cebola');

    expect($order->source)->toBe(OrderSource::Table)
        ->and($order->fulfillment)->toBe(Fulfillment::Table)
        ->and($order->command_id)->toBe($command->id)
        ->and($order->payments()->count())->toBe(0)
        ->and($order->customer_name)->toBe('Mesa 1')
        ->and($command->itemsSubtotal()->cents)->toBe(8400)
        ->and(Order::open()->whereKey($order->id)->exists())->toBeTrue(); // aparece no painel

    expect(Command::open()->where('table_id', $t1->id)->count())->toBe(1);
});

it('fecha a conta com taxa de serviço e pagamento dividido, com troco no dinheiro', function () {
    ['tenant' => $tenant, 'burger' => $burger, 't1' => $t1] = salon();
    $garcom = userIn($tenant, ['garcom']);
    $caixa = userIn($tenant, ['caixa']);
    $manage = app(ManageCommand::class);
    $session = openCash($caixa, '50');

    $command = $manage->open($t1, null, 2, $garcom);
    $order = $manage->addItems($command, [line($burger, 3)], $garcom); // 90 + 10% = 99
    $manage->requestBill($command, $garcom);

    expect(fn () => $manage->close($command->fresh(), $caixa, [['method' => 'pix', 'amount' => Money::parse('50')]], true))
        ->toThrow(ValidationException::class, 'Faltam');
    expect(fn () => $manage->close($command->fresh(), $caixa, [['method' => 'pix', 'amount' => Money::parse('120')]], true))
        ->toThrow(ValidationException::class, 'Só pagamento em dinheiro');

    $result = $manage->close($command->fresh(), $caixa, [
        ['method' => 'pix', 'amount' => Money::parse('40')],
        ['method' => 'cash', 'amount' => Money::parse('100')], // paga 59 em dinheiro, troco 41
    ], true);

    $command->refresh();
    expect($result['change']->cents)->toBe(4100)
        ->and($command->status)->toBe(CommandStatus::Closed)
        ->and($command->service_fee_cents->cents)->toBe(900)
        ->and($command->total_cents->cents)->toBe(9900)
        ->and($session->expectedByMethod()['pix']->cents)->toBe(4000)
        ->and($session->expectedByMethod()['cash']->cents)->toBe(5000 + 5900)
        ->and($order->fresh()->status)->toBe(OrderStatus::Completed)
        ->and(Command::open()->where('table_id', $t1->id)->exists())->toBeFalse();

    // Fechada, não fecha de novo nem recebe itens.
    expect(fn () => $manage->addItems($command, [line($burger)], $garcom))->toThrow(ValidationException::class);
});

it('só quem tem permissão tira a taxa de serviço ou dá desconto', function () {
    ['tenant' => $tenant, 'burger' => $burger, 't1' => $t1] = salon();
    $caixa = userIn($tenant, ['caixa']);
    $gerente = userIn($tenant, ['gerente']);
    $manage = app(ManageCommand::class);
    openCash($caixa);
    openCash($gerente);

    $command = $manage->open($t1, null, 1, $caixa);
    $manage->addItems($command, [line($burger)], $caixa);

    expect(fn () => $manage->close($command->fresh(), $caixa, [['method' => 'pix', 'amount' => Money::parse('30')]], false))
        ->toThrow(ValidationException::class, 'permissão');

    $manage->close($command->fresh(), $gerente, [['method' => 'pix', 'amount' => Money::parse('25')]], false, Money::parse('5'));
    expect($command->fresh()->total_cents->cents)->toBe(2500);
});

it('troca de mesa, junta comandas e cancela só comanda vazia', function () {
    ['tenant' => $tenant, 'burger' => $burger, 'soda' => $soda, 't1' => $t1, 't2' => $t2] = salon();
    $garcom = userIn($tenant, ['garcom']);
    $manage = app(ManageCommand::class);
    $t3 = DiningTable::create(['name' => '3']);

    $a = $manage->open($t1, null, 2, $garcom);
    $b = $manage->open($t2, null, 2, $garcom);
    $manage->addItems($a, [line($burger)], $garcom);
    $manage->addItems($b, [line($soda, 2)], $garcom);

    expect(fn () => $manage->transfer($a, $t2, $garcom))->toThrow(ValidationException::class, 'não está livre');
    $manage->transfer($a, $t3, $garcom);
    expect($a->fresh()->table_id)->toBe($t3->id);

    $manage->merge($a->fresh(), $b->fresh(), $garcom);
    expect($a->fresh()->itemsSubtotal()->cents)->toBe(4600)
        ->and($a->fresh()->people)->toBe(4)
        ->and($b->fresh()->status)->toBe(CommandStatus::Cancelled)
        ->and(DiningTable::find($t2->id)->openCommand()->exists())->toBeFalse();

    expect(fn () => $manage->cancel($a->fresh(), $garcom, 'engano'))->toThrow(ValidationException::class);
    $empty = $manage->open(null, 'Cartão 15', 1, $garcom);
    $manage->cancel($empty, $garcom, 'engano');
    expect($empty->fresh()->status)->toBe(CommandStatus::Cancelled);
});

it('item cancelado sai da conta', function () {
    ['tenant' => $tenant, 'burger' => $burger, 'soda' => $soda, 't1' => $t1] = salon();
    $gerente = userIn($tenant, ['gerente']);
    $manage = app(ManageCommand::class);

    $command = $manage->open($t1, null, 1, $gerente);
    $manage->addItems($command, [line($burger)], $gerente);
    $wrong = $manage->addItems($command, [line($soda)], $gerente);
    app(ChangeOrderStatus::class)->handle($wrong, OrderStatus::Cancelled, $gerente, 'Lançado errado');

    expect($command->itemsSubtotal()->cents)->toBe(3000);
});

it('garçom lança pela tela, mas só quem opera caixa fecha a conta', function () {
    ['tenant' => $tenant, 'burger' => $burger, 't1' => $t1] = salon();
    $garcom = userIn($tenant, ['garcom']);
    $caixa = userIn($tenant, ['caixa']);
    openCash($caixa);

    Livewire::actingAs($garcom)->test(Map::class)->call('startOpen', $t1->id)->set('people', 2)->call('open')->assertHasNoErrors();
    $command = Command::open()->where('table_id', $t1->id)->firstOrFail();

    Livewire::actingAs($garcom)->test(CommandPage::class, ['command' => $command])
        ->call('addProduct', $burger->id)
        ->call('send')
        ->assertHasNoErrors()
        ->call('close')
        ->assertForbidden();

    Livewire::actingAs($caixa)->test(CommandPage::class, ['command' => $command])
        ->set('payments', [['method' => 'cash', 'amount' => '50']])
        ->call('close')
        ->assertHasNoErrors()
        ->assertSet('change', 'R$ 17,00');

    expect($command->fresh()->status)->toBe(CommandStatus::Closed);
});

it('libera mesas, PDV e caixa conforme o perfil', function () {
    $tenant = tenantBySlug('teste1');
    $checks = [
        ['garcom', '/mesas', 200], ['garcom', '/mesas/cadastro', 403], ['garcom', '/caixa', 403], ['garcom', '/balcao', 403],
        ['caixa', '/caixa', 200], ['caixa', '/balcao', 200], ['caixa', '/caixa/sessoes', 403],
        ['cozinha', '/mesas', 403], ['gerente', '/caixa/sessoes', 200], ['gerente', '/mesas/cadastro', 200],
    ];

    foreach ($checks as [$role, $path, $status]) {
        $this->flushSession();
        $this->actingAs(createTenantUser($tenant, [$role]))->get(tenantUrl($tenant, $path))->assertStatus($status);
    }
});

it('operador não vê o relatório do caixa de outro; a gerência vê', function () {
    ['tenant' => $tenant] = salon();
    $a = userIn($tenant, ['caixa']);
    $b = userIn($tenant, ['caixa']);
    $gerente = userIn($tenant, ['gerente']);
    $session = openCash($a, '20');
    app(TenantContext::class)->reset();

    $this->flushSession();
    $this->actingAs($a)->get(tenantUrl($tenant, '/caixa/sessoes/'.$session->id))->assertOk()->assertSee('R$ 20,00');
    $this->flushSession();
    $this->actingAs($b)->get(tenantUrl($tenant, '/caixa/sessoes/'.$session->id))->assertForbidden();
    $this->flushSession();
    $this->actingAs($gerente)->get(tenantUrl($tenant, '/caixa/sessoes/'.$session->id))->assertOk();
});

it('abre e fecha o caixa pela tela', function () {
    ['tenant' => $tenant] = salon();
    $caixa = userIn($tenant, ['caixa']);
    $register = CashRegister::create(['name' => 'Tela']);

    Livewire::actingAs($caixa)->test(Current::class)
        ->set('registerId', $register->id)
        ->set('opening', '80,00')
        ->call('open')
        ->assertHasNoErrors()
        ->set('movementType', 'withdrawal')
        ->set('movementAmount', '30')
        ->set('movementReason', 'Banco')
        ->call('addMovement')
        ->assertHasNoErrors()
        ->call('startClosing')
        ->set('counted.cash', '50')
        ->call('close')
        ->assertHasNoErrors();

    $session = CashSession::where('opened_by', $caixa->id)->firstOrFail();
    expect($session->status)->toBe('closed')->and($session->difference_cents->cents)->toBe(0);
});
