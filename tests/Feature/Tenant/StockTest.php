<?php

use App\Livewire\Tenant\Stock\Index as StockIndex;
use App\Livewire\Tenant\Stock\RecipeEditor;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Menu\Models\Addon;
use App\Modules\Menu\Models\AddonGroup;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Combo;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Stock\Actions\SaveRecipe;
use App\Modules\Stock\Models\Recipe;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockMovement;
use App\Modules\Stock\Models\Unit;
use App\Modules\Stock\MovementType;
use App\Modules\Stock\StockLedger;
use App\Modules\Tables\Actions\ManageCommand;
use App\Modules\Tables\Models\DiningTable;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** Pizzaria com ficha técnica: muçarela (kg), caixa (un), bacon (kg). */
function kitchen(): array
{
    $tenant = provisionTenant('estoque');
    activateTenant($tenant);
    OpeningHour::query()->update(['is_closed' => false, 'opens_at' => '00:00', 'closes_at' => '23:59']);

    $kg = Unit::where('code', 'kg')->first();
    $g = Unit::where('code', 'g')->first();
    $un = Unit::where('code', 'un')->first();

    $cheese = StockItem::create(['name' => 'Muçarela', 'unit_id' => $kg->id, 'min_quantity' => '1.000']);
    $box = StockItem::create(['name' => 'Caixa de pizza', 'unit_id' => $un->id]);
    $bacon = StockItem::create(['name' => 'Bacon', 'unit_id' => $kg->id]);

    $ledger = app(StockLedger::class);
    $ledger->purchase($cheese, '5.000', Money::parse('200'), null); // R$ 40/kg
    $ledger->purchase($box, '100.000', Money::parse('150'), null);  // R$ 1,50/un
    $ledger->purchase($bacon, '2.000', Money::parse('80'), null);   // R$ 40/kg

    $category = Category::create(['name' => 'Pizzas']);
    $pizza = Product::create(['category_id' => $category->id, 'name' => 'Pizza Muçarela', 'price_cents' => Money::parse('50')]);
    $soda = Product::create(['category_id' => $category->id, 'name' => 'Refri', 'price_cents' => Money::parse('10')]);
    $extras = AddonGroup::create(['name' => 'Extras', 'min_choices' => 0, 'max_choices' => 2]);
    $extraBacon = Addon::create(['addon_group_id' => $extras->id, 'name' => 'Bacon extra', 'price_cents' => Money::parse('6')]);
    $pizza->addonGroups()->sync([$extras->id => ['position' => 0]]);

    $save = app(SaveRecipe::class);
    $save->handle($pizza, [
        ['stock_item_id' => $cheese->id, 'quantity' => '200.000', 'unit_id' => $g->id],
        ['stock_item_id' => $box->id, 'quantity' => '1.000', 'unit_id' => $un->id],
    ]);
    $save->handle($extraBacon, [['stock_item_id' => $bacon->id, 'quantity' => '50.000', 'unit_id' => $g->id]]);

    return compact('tenant', 'cheese', 'box', 'bacon', 'pizza', 'soda', 'extraBacon', 'kg', 'g', 'un');
}

function pizzaOrder(array $k, int $quantity = 2, bool $withBacon = true)
{
    return app(PlaceOrder::class)->handle([
        'lines' => [['type' => 'product', 'id' => $k['pizza']->id, 'quantity' => $quantity, 'addons' => $withBacon ? [$k['extraBacon']->id] : []]],
        'fulfillment' => 'pickup',
        'customer' => ['name' => 'Cliente', 'phone' => '11966665555'],
        'payment_method' => 'pix',
    ], OrderSource::Online);
}

it('converte unidades compatíveis e recusa as incompatíveis', function () {
    ['kg' => $kg, 'g' => $g, 'un' => $un] = kitchen();

    expect($g->convert('200', $kg))->toBe('0.200')
        ->and($kg->convert('1.5', $g))->toBe('1500.000')
        ->and(fn () => $g->convert('1', $un))->toThrow(InvalidArgumentException::class);
});

it('recalcula o custo médio ponderado a cada entrada', function () {
    ['cheese' => $cheese] = kitchen();
    $ledger = app(StockLedger::class);

    // 5 kg a R$ 40 + 5 kg a R$ 50 → R$ 45/kg
    $ledger->purchase($cheese, '5.000', Money::parse('250'), null, 'NF 10');
    $cheese->refresh();

    expect($cheese->quantity)->toBe('10.000')
        ->and($cheese->average_cost_cents)->toBe(4500)
        ->and($cheese->stockValue()->format())->toBe('R$ 450,00');
});

it('registra perda com motivo e ajusta pela contagem', function () {
    ['cheese' => $cheese] = kitchen();
    $ledger = app(StockLedger::class);

    expect(fn () => $ledger->loss($cheese, '0.500', null, ''))->toThrow(ValidationException::class);
    $ledger->loss($cheese, '0.500', null, 'Venceu');
    expect($cheese->fresh()->quantity)->toBe('4.500');

    $adjust = $ledger->count($cheese, '4.200', null);
    expect($adjust->type)->toBe(MovementType::Adjustment)
        ->and($adjust->quantity)->toBe('-0.300')
        ->and($cheese->fresh()->quantity)->toBe('4.200')
        ->and($ledger->count($cheese, '4.200', null))->toBeNull();

    // O saldo sempre bate com a soma das movimentações.
    expect(StockMovement::where('stock_item_id', $cheese->id)->sum('quantity'))->toEqual('4.200');
});

it('calcula o custo da ficha e valida unidades', function () {
    $k = kitchen();
    $recipe = Recipe::with('items.stockItem.unit', 'items.unit')->where('product_id', $k['pizza']->id)->first();

    // 0,2 kg × R$ 40 + 1 × R$ 1,50 = R$ 9,50
    expect($recipe->cost()->format())->toBe('R$ 9,50');

    expect(fn () => app(SaveRecipe::class)->handle($k['soda'], [['stock_item_id' => $k['box']->id, 'quantity' => '1', 'unit_id' => $k['kg']->id]]))
        ->toThrow(ValidationException::class, 'unidade compatível');
});

it('baixa o estoque quando o pedido entra em preparo, uma vez só', function () {
    $k = kitchen();
    $order = pizzaOrder($k); // 2 pizzas com bacon extra

    expect(StockMovement::where('order_id', $order->id)->exists())->toBeFalse();

    $change = app(ChangeOrderStatus::class);
    $change->handle($order, OrderStatus::Preparing);

    expect($k['cheese']->fresh()->quantity)->toBe('4.600')  // 5 - 2×0,2
        ->and($k['box']->fresh()->quantity)->toBe('98.000')
        ->and($k['bacon']->fresh()->quantity)->toBe('1.900') // 2 - 2×0,05
        ->and($order->fresh()->stock_deducted_at)->not->toBeNull()
        ->and(StockMovement::where('order_id', $order->id)->where('type', MovementType::Sale)->count())->toBe(3);

    $change->handle($order->fresh(), OrderStatus::Ready);
    $change->handle($order->fresh(), OrderStatus::Completed);

    expect($k['cheese']->fresh()->quantity)->toBe('4.600');
});

it('cancelado antes do preparo não mexe; depois vira perda', function () {
    $k = kitchen();
    $change = app(ChangeOrderStatus::class);

    $early = pizzaOrder($k, 1, false);
    $change->handle($early, OrderStatus::Cancelled, null, 'desistiu');
    expect(StockMovement::where('order_id', $early->id)->exists())->toBeFalse()
        ->and($k['cheese']->fresh()->quantity)->toBe('5.000');

    $late = pizzaOrder($k, 1, false);
    $change->handle($late, OrderStatus::Preparing);
    $change->handle($late->fresh(), OrderStatus::Cancelled, null, 'cliente não veio');

    expect($k['cheese']->fresh()->quantity)->toBe('4.800')
        ->and(StockMovement::where('order_id', $late->id)->pluck('type')->unique()->values()->all())->toBe([MovementType::Loss]);
});

it('combo baixa a ficha de cada produto incluído', function () {
    $k = kitchen();
    $combo = Combo::create(['name' => 'Combo 2 pizzas', 'price_cents' => Money::parse('90')]);
    $combo->items()->createMany([['product_id' => $k['pizza']->id, 'quantity' => 2], ['product_id' => $k['soda']->id, 'quantity' => 1]]);

    $order = app(PlaceOrder::class)->handle([
        'lines' => [['type' => 'combo', 'id' => $combo->id, 'quantity' => 1]],
        'fulfillment' => 'pickup', 'customer' => ['name' => 'C', 'phone' => '11966665555'], 'payment_method' => 'pix',
    ], OrderSource::Online);

    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Preparing);

    expect($k['cheese']->fresh()->quantity)->toBe('4.600')
        ->and($k['box']->fresh()->quantity)->toBe('98.000');
});

it('fechar a conta da mesa baixa o que não passou pela cozinha', function () {
    $k = kitchen();
    $gerente = createTenantUser($k['tenant'], ['gerente']);
    app(\App\Modules\Cash\CashLedger::class)->open(\App\Modules\Cash\Models\CashRegister::create(['name' => 'C9']), $gerente, Money::zero());
    $manage = app(ManageCommand::class);

    $command = $manage->open(DiningTable::create(['name' => '9']), null, 1, $gerente);
    $manage->addItems($command, [['type' => 'product', 'id' => $k['pizza']->id, 'quantity' => 1]], $gerente);
    $manage->close($command->fresh(), $gerente, [['method' => 'pix', 'amount' => Money::parse('55')]], true);

    expect($k['cheese']->fresh()->quantity)->toBe('4.800');
});

it('estoque pode ficar negativo; com a opção ligada, o produto fica esgotado', function () {
    $k = kitchen();
    app(StockLedger::class)->count($k['cheese'], '0.300', null);
    $change = app(ChangeOrderStatus::class);

    $change->handle(pizzaOrder($k, 2, false), OrderStatus::Preparing);
    expect($k['cheese']->fresh()->quantity)->toBe('-0.100')
        ->and($k['pizza']->fresh()->is_available)->toBeTrue();

    Setting::write('stock_auto_sold_out', true);
    app(StockLedger::class)->count($k['cheese'], '0.200', null);
    $change->handle(pizzaOrder($k, 1, false), OrderStatus::Preparing);

    expect($k['cheese']->fresh()->quantity)->toBe('0.000')
        ->and($k['pizza']->fresh()->is_available)->toBeFalse()
        ->and($k['soda']->fresh()->is_available)->toBeTrue();
});

it('gerencia insumos e fichas pela tela', function () {
    $k = kitchen();
    $gerente = createTenantUser($k['tenant'], ['gerente']);

    Livewire::actingAs($gerente)->test(StockIndex::class)
        ->set('name', 'Farinha')->set('unitId', $k['kg']->id)->set('minQuantity', '5')
        ->call('create')->assertHasNoErrors();
    $flour = StockItem::where('name', 'Farinha')->firstOrFail();

    Livewire::actingAs($gerente)->test(StockIndex::class)
        ->call('startMovement', $flour->id, 'purchase')
        ->set('quantity', '25')->set('cost', '100,00')
        ->call('saveMovement')->assertHasNoErrors()
        ->call('startMovement', $flour->id, 'loss')
        ->set('quantity', '1,5')->set('reason', 'Umidade')
        ->call('saveMovement')->assertHasNoErrors();

    expect($flour->fresh()->quantity)->toBe('23.500')->and($flour->fresh()->average_cost_cents)->toBe(400);

    Livewire::actingAs($gerente)->test(RecipeEditor::class, ['product' => $k['soda']])
        ->set('lines', [['stock_item_id' => $k['box']->id, 'quantity' => '1', 'unit_id' => $k['un']->id]])
        ->call('save')->assertHasNoErrors()->assertRedirect(route('stock.recipes'));

    expect(Recipe::where('product_id', $k['soda']->id)->exists())->toBeTrue();
});

it('só quem gerencia estoque acessa e o alerta de mínimo aparece no início', function () {
    $k = kitchen();
    app(StockLedger::class)->count($k['cheese'], '0.500', null); // mínimo é 1 kg
    $caixa = createTenantUser($k['tenant'], ['caixa']);
    $gerente = createTenantUser($k['tenant'], ['gerente']);
    app(TenantContext::class)->reset();

    $this->flushSession();
    $this->actingAs($caixa)->get(tenantUrl($k['tenant'], '/estoque'))->assertForbidden();
    $this->flushSession();
    $this->actingAs($caixa)->get(tenantUrl($k['tenant'], '/'))->assertDontSee('Estoque baixo');
    $this->flushSession();
    $this->actingAs($gerente)->get(tenantUrl($k['tenant'], '/'))->assertSee('Estoque baixo')->assertSee('Muçarela');
    $this->flushSession();
    $this->actingAs($gerente)->get(tenantUrl($k['tenant'], '/estoque/fichas'))->assertOk()->assertSee('R$ 9,50');
});
