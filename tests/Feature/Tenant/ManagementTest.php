<?php

use App\Livewire\Tenant\Customers\Show as CustomerShow;
use App\Livewire\Tenant\Finance\EntryForm;
use App\Livewire\Tenant\Finance\Index as FinanceIndex;
use App\Livewire\Tenant\Orders\Counter;
use App\Livewire\Tenant\Stock\Index as StockIndex;
use App\Modules\Access\Models\User;
use App\Modules\Cash\CashLedger;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Customers\Models\Customer;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Finance\FinanceBook;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Reports\SalesReport;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\Unit;
use App\Modules\Tables\Actions\ManageCommand;
use App\Modules\Tables\Models\DiningTable;
use App\Support\Money;
use App\Tenancy\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/** Tenant novo com dois produtos e horário aberto. */
function shop(): array
{
    $tenant = provisionTenant('gestao');
    activateTenant($tenant);
    OpeningHour::query()->update(['is_closed' => false, 'opens_at' => '00:00', 'closes_at' => '23:59']);
    $category = Category::create(['name' => 'Lanches']);

    return [
        'tenant' => $tenant,
        'burger' => Product::create(['category_id' => $category->id, 'name' => 'X-Burger', 'price_cents' => Money::parse('30')]),
        'fries' => Product::create(['category_id' => $category->id, 'name' => 'Batata', 'price_cents' => Money::parse('15')]),
        'gerente' => createTenantUser($tenant, ['gerente']),
    ];
}

function onlineOrder(Product $product, int $quantity, string $phone = '11955554444', string $fulfillment = 'pickup'): Order
{
    return app(PlaceOrder::class)->handle([
        'lines' => [['type' => 'product', 'id' => $product->id, 'quantity' => $quantity]],
        'fulfillment' => $fulfillment,
        'customer' => ['name' => 'Joana', 'phone' => $phone],
        'address' => ['street' => 'Rua A', 'number' => '10', 'district' => 'Centro'],
        'payment_method' => 'pix',
    ], OrderSource::Online);
}

function openCashFor(User $user): void
{
    app(CashLedger::class)->open(CashRegister::create(['name' => 'Caixa '.uniqid()]), $user, Money::zero());
}

it('mostra o histórico do cliente e permite apagar os dados pessoais (LGPD)', function () {
    ['burger' => $burger, 'gerente' => $gerente, 'tenant' => $tenant] = shop();
    $first = onlineOrder($burger, 2, '11955554444', 'delivery');
    onlineOrder($burger, 1);
    $customer = Customer::where('phone', '11955554444')->firstOrFail();

    app(TenantContext::class)->reset();
    $this->actingAs($gerente)->get(tenantUrl($tenant, '/clientes?q=5555'))->assertOk()->assertSee('Joana')->assertSee('R$ 90,00');
    activateTenant($tenant);

    Livewire::actingAs($gerente)->test(CustomerShow::class, ['customer' => $customer])
        ->assertSee('2 pedido(s)', false)->assertSee('ticket médio R$ 45,00', false)
        ->set('notes', 'Alérgica a amendoim')->call('save')->assertHasNoErrors()
        ->call('anonymize')->assertRedirect(route('customers.index'));

    expect(Customer::find($customer->id))->toBeNull()
        ->and(Customer::withTrashed()->find($customer->id)->name)->toBe('Cliente removido')
        ->and(Customer::withTrashed()->find($customer->id)->addresses()->count())->toBe(0)
        ->and($first->fresh()->customer_phone)->toBeNull()
        ->and($first->fresh()->address_street)->toBeNull()
        ->and($first->fresh()->total_cents->cents)->toBe(6000); // valores ficam
});

it('no PDV, o telefone preenche nome, endereço e observações do cliente', function () {
    ['burger' => $burger, 'gerente' => $gerente] = shop();
    onlineOrder($burger, 1, '11955554444', 'delivery');
    Customer::where('phone', '11955554444')->update(['notes' => 'Sem cebola sempre']);

    Livewire::actingAs($gerente)->test(Counter::class)
        ->set('customerPhone', '(11) 95555-4444')
        ->assertSet('customerName', 'Joana')
        ->assertSet('address.street', 'Rua A')
        ->assertSet('customerNotes', 'Sem cebola sempre');
});

it('lança, paga e repete contas; o saldo da conta acompanha', function () {
    ['gerente' => $gerente] = shop();
    $bank = FinancialAccount::where('type', 'bank')->firstOrFail();
    $book = app(FinanceBook::class);

    Livewire::actingAs($gerente)->test(EntryForm::class)
        ->set('kind', 'payable')->set('description', 'Aluguel')->set('amount', '2.500,00')
        ->set('dueDate', '2026-10-05')->set('monthly', true)
        ->call('save')->assertHasNoErrors();

    $rent = FinancialEntry::where('description', 'Aluguel')->firstOrFail();
    expect($rent->statusLabel('2026-10-09'))->toBe('Vencido');

    Livewire::actingAs($gerente)->test(FinanceIndex::class)
        ->call('startPay', $rent->id)->set('accountId', $bank->id)->set('paidOn', '2026-10-09')
        ->call('pay')->assertHasNoErrors();

    $next = FinancialEntry::where('description', 'Aluguel')->where('status', 'open')->firstOrFail();
    expect($rent->fresh()->status)->toBe('paid')
        ->and($next->due_date->toDateString())->toBe('2026-11-05')
        ->and($bank->balance()->cents)->toBe(-250000);

    $book->cancel($next, $gerente);
    expect($next->fresh()->status)->toBe('cancelled');
});

it('fechar o caixa lança os recebimentos no financeiro, por forma de pagamento e uma vez só', function () {
    ['burger' => $burger, 'fries' => $fries, 'gerente' => $gerente] = shop();
    openCashFor($gerente);
    $placeOrder = app(PlaceOrder::class);

    $placeOrder->handle(['lines' => [['type' => 'product', 'id' => $burger->id, 'quantity' => 2]], 'fulfillment' => 'counter', 'payment_method' => 'pix'], OrderSource::Counter, $gerente);
    $placeOrder->handle(['lines' => [['type' => 'product', 'id' => $fries->id, 'quantity' => 1]], 'fulfillment' => 'counter', 'payment_method' => 'cash'], OrderSource::Counter, $gerente);
    $refunded = $placeOrder->handle(['lines' => [['type' => 'product', 'id' => $fries->id, 'quantity' => 2]], 'fulfillment' => 'counter', 'payment_method' => 'pix'], OrderSource::Counter, $gerente);
    app(ChangeOrderStatus::class)->handle($refunded, OrderStatus::Cancelled, $gerente, 'erro');

    $ledger = app(CashLedger::class);
    $session = $ledger->currentFor($gerente);
    $ledger->close($session, $gerente, $session->expectedByMethod());

    $entries = FinancialEntry::where('source_ref', 'like', "cash_session:{$session->id}:%")->get()->keyBy(fn ($e) => explode(':', $e->source_ref)[2]);

    expect($entries)->toHaveCount(2)
        ->and($entries['pix']->amount_cents->cents)->toBe(6000) // 60 + 30 − 30 estornado
        ->and($entries['pix']->status)->toBe('paid')
        ->and($entries['pix']->account->type)->toBe('bank')
        ->and($entries['cash']->amount_cents->cents)->toBe(1500)
        ->and($entries['cash']->account->type)->toBe('cash');

    app(FinanceBook::class)->recordCashSession($session->fresh());
    expect(FinancialEntry::where('source_ref', 'like', "cash_session:{$session->id}:%")->count())->toBe(2);
});

it('entrada de estoque pode gerar a conta a pagar', function () {
    ['gerente' => $gerente] = shop();
    $item = StockItem::create(['name' => 'Pão', 'unit_id' => Unit::where('code', 'un')->value('id')]);

    Livewire::actingAs($gerente)->test(StockIndex::class)
        ->call('startMovement', $item->id, 'purchase')
        ->set('quantity', '100')->set('cost', '80')
        ->set('createPayable', true)->set('supplier', 'Padaria Central')->set('dueDate', '2026-10-20')
        ->call('saveMovement')->assertHasNoErrors();

    $entry = FinancialEntry::where('counterparty', 'Padaria Central')->firstOrFail();
    expect($entry->kind)->toBe('payable')
        ->and($entry->amount_cents->cents)->toBe(8000)
        ->and($entry->category->name)->toBe('Fornecedores')
        ->and($entry->due_date->toDateString())->toBe('2026-10-20');
});

it('soma vendas por canal sem contar duas vezes os lançamentos de mesa', function () {
    ['burger' => $burger, 'fries' => $fries, 'gerente' => $gerente] = shop();
    openCashFor($gerente);

    onlineOrder($burger, 1); // 30 online
    app(PlaceOrder::class)->handle(['lines' => [['type' => 'product', 'id' => $fries->id, 'quantity' => 2]], 'fulfillment' => 'counter', 'payment_method' => 'cash'], OrderSource::Counter, $gerente); // 30 balcão
    $cancelled = onlineOrder($burger, 3);
    app(ChangeOrderStatus::class)->handle($cancelled, OrderStatus::Cancelled, $gerente, 'teste');

    $manage = app(ManageCommand::class);
    $command = $manage->open(DiningTable::create(['name' => '1']), null, 2, $gerente);
    $manage->addItems($command, [['type' => 'product', 'id' => $burger->id, 'quantity' => 2]], $gerente); // 60 + 10% = 66
    $manage->close($command->fresh(), $gerente, [['method' => 'pix', 'amount' => Money::parse('66')]], true);

    $report = app(SalesReport::class);
    $today = CarbonImmutable::now($report->timezone)->toDateString();
    [$from, $to] = $report->range($today, $today);
    $summary = $report->summary($from, $to);

    expect($summary['revenue']->cents)->toBe(12600)
        ->and($summary['count'])->toBe(3)
        ->and($summary['average']->cents)->toBe(4200)
        ->and($summary['channels']['online']['total']->cents)->toBe(3000)
        ->and($summary['channels']['counter']['total']->cents)->toBe(3000)
        ->and($summary['channels']['table']['total']->cents)->toBe(6600)
        ->and($summary['service_fee']->cents)->toBe(600);

    $top = $report->topProducts($from, $to);
    expect($top->firstWhere('name', 'X-Burger')['quantity'])->toBe(3) // 1 online + 2 mesa; cancelado fora
        ->and($report->cancellations($from, $to))->toHaveCount(1)
        ->and(array_sum($report->hourly($from, $to)))->toBe(3)
        ->and($report->byPaymentMethod($from, $to)->firstWhere('method', 'Pix')['total']->cents)->toBe(6600);
});

it('exporta vendas em CSV e protege relatórios, financeiro e clientes por permissão', function () {
    ['burger' => $burger, 'tenant' => $tenant, 'gerente' => $gerente] = shop();
    onlineOrder($burger, 1);
    $caixa = createTenantUser($tenant, ['caixa']);
    $cozinha = createTenantUser($tenant, ['cozinha']);
    app(TenantContext::class)->reset();
    $today = CarbonImmutable::now('America/Sao_Paulo')->toDateString();

    $csv = $this->actingAs($gerente)->get(tenantUrl($tenant, "/relatorios/exportar/vendas?from={$today}&to={$today}"));
    $csv->assertOk();
    expect($csv->streamedContent())->toStartWith("\xEF\xBB\xBF")->toContain('Dia;Pedidos;"Ticket médio";Vendas')->toContain('30,00');

    foreach ([[$caixa, '/clientes', 200], [$caixa, '/financeiro', 403], [$caixa, '/relatorios', 403], [$cozinha, '/clientes', 403], [$gerente, '/financeiro', 200], [$gerente, '/relatorios?tab=products', 200]] as [$user, $path, $status]) {
        $this->flushSession();
        $this->actingAs($user)->get(tenantUrl($tenant, $path))->assertStatus($status);
    }

    $this->flushSession();
    $this->actingAs($gerente)->get(tenantUrl($tenant, '/'))->assertSee('Vendas hoje')->assertSee('R$ 30,00');
    $this->flushSession();
    $this->actingAs($caixa)->get(tenantUrl($tenant, '/'))->assertDontSee('Vendas hoje');
});
