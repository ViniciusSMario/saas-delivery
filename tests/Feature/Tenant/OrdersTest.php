<?php

use App\Livewire\Tenant\Orders\Board;
use App\Livewire\Tenant\Orders\Counter;
use App\Livewire\Tenant\Orders\Show;
use App\Modules\Customers\Models\Customer;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Menu\Models\Addon;
use App\Modules\Menu\Models\AddonGroup;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Combo;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Support\Money;
use App\Tenancy\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * Cardápio mínimo para pedidos: pizza com borda obrigatória (1) e extras (até 2),
 * refrigerante e um combo.
 *
 * @return array<string, mixed>
 */
function orderMenu(Tenant $tenant): array
{
    return inTenant($tenant, function () {
        $category = Category::create(['name' => 'Pedidos '.Str::random(4)]);
        $pizza = Product::create(['category_id' => $category->id, 'name' => 'Pizza '.Str::random(4), 'price_cents' => Money::parse('40')]);
        $soda = Product::create(['category_id' => $category->id, 'name' => 'Refri '.Str::random(4), 'price_cents' => Money::parse('10')]);

        $crust = AddonGroup::create(['name' => 'Borda', 'min_choices' => 1, 'max_choices' => 1]);
        $plain = Addon::create(['addon_group_id' => $crust->id, 'name' => 'Tradicional', 'price_cents' => Money::zero()]);
        $cheese = Addon::create(['addon_group_id' => $crust->id, 'name' => 'Catupiry', 'price_cents' => Money::parse('8')]);
        $extras = AddonGroup::create(['name' => 'Extras', 'min_choices' => 0, 'max_choices' => 2]);
        $bacon = Addon::create(['addon_group_id' => $extras->id, 'name' => 'Bacon', 'price_cents' => Money::parse('5')]);
        $egg = Addon::create(['addon_group_id' => $extras->id, 'name' => 'Ovo', 'price_cents' => Money::parse('3')]);
        $olive = Addon::create(['addon_group_id' => $extras->id, 'name' => 'Azeitona', 'price_cents' => Money::parse('2')]);
        $pizza->addonGroups()->sync([$crust->id => ['position' => 0], $extras->id => ['position' => 1]]);

        $combo = Combo::create(['name' => 'Combo '.Str::random(4), 'price_cents' => Money::parse('45')]);
        $combo->items()->createMany([['product_id' => $pizza->id, 'quantity' => 1], ['product_id' => $soda->id, 'quantity' => 1]]);

        $otherGroup = AddonGroup::create(['name' => 'Molhos', 'min_choices' => 0, 'max_choices' => 1]);
        $sauce = Addon::create(['addon_group_id' => $otherGroup->id, 'name' => 'Barbecue', 'price_cents' => Money::parse('1')]);

        return compact('category', 'pizza', 'soda', 'crust', 'plain', 'cheese', 'extras', 'bacon', 'egg', 'olive', 'combo', 'sauce');
    });
}

/** Pedido online válido (retirada, Pix), com ajustes. */
function onlineOrderInput(array $menu, array $overrides = []): array
{
    return array_replace_recursive([
        'lines' => [
            ['type' => 'product', 'id' => $menu['pizza']->id, 'quantity' => 2, 'addons' => [$menu['cheese']->id, $menu['bacon']->id], 'notes' => 'sem cebola'],
            ['type' => 'combo', 'id' => $menu['combo']->id, 'quantity' => 1],
        ],
        'fulfillment' => 'pickup',
        'customer' => ['name' => 'Ana Cliente', 'phone' => '(11) 98888-7777'],
        'payment_method' => 'pix',
    ], $overrides);
}

function placeOnline(Tenant $tenant, array $input): Order
{
    return inTenant($tenant, fn () => app(PlaceOrder::class)->handle($input, OrderSource::Online));
}

beforeEach(function () {
    $this->a = tenantBySlug('teste1');
    $this->b = tenantBySlug('teste2');
    // Sexta-feira, 15h em São Paulo. Outros testes mexem nos horários de teste1/teste2,
    // então aqui eles ficam abertos o dia todo.
    $this->travelTo(CarbonImmutable::parse('2026-10-09 15:00', 'America/Sao_Paulo'));
    foreach ([$this->a, $this->b] as $tenant) {
        inTenant($tenant, function () {
            OpeningHour::query()->update(['is_closed' => false, 'opens_at' => '00:00', 'closes_at' => '23:59']);

            foreach (['accepts_online_orders' => true, 'accepts_delivery' => true, 'accepts_pickup' => true, 'min_order_cents' => 0, 'delivery_fee_cents' => 0] as $key => $value) {
                Setting::write($key, $value);
            }
        });
    }
});

it('cria o pedido online com retrato dos itens, totais, histórico e pagamento a receber', function () {
    $menu = orderMenu($this->a);
    $order = placeOnline($this->a, onlineOrderInput($menu));

    inTenant($this->a, function () use ($order, $menu) {
        $order->load('items.addons', 'payments', 'history');
        [$pizza, $combo] = $order->items->all();

        // (40 + 8 + 5) × 2 = 106; combo 45 → 151
        expect($order->status)->toBe(OrderStatus::Received)
            ->and($order->source)->toBe(OrderSource::Online)
            ->and($order->number)->toBeGreaterThan(0)
            ->and($order->public_token)->toHaveLength(32)
            ->and($pizza->total_cents->cents)->toBe(10600)
            ->and($pizza->addons->pluck('name')->all())->toBe(['Catupiry', 'Bacon'])
            ->and($pizza->notes)->toBe('sem cebola')
            ->and($combo->description)->toContain($menu['soda']->name)
            ->and($order->subtotal_cents->cents)->toBe(15100)
            ->and($order->total_cents->cents)->toBe(15100)
            ->and($order->payments->first()->status)->toBe(PaymentStatus::Pending)
            ->and($order->history->first()->to_status)->toBe(OrderStatus::Received)
            ->and($order->customer_phone)->toBe('11988887777')
            ->and(Customer::where('phone', '11988887777')->value('name'))->toBe('Ana Cliente');

        // O retrato não muda quando o cardápio muda.
        $menu['pizza']->update(['name' => 'Outro nome', 'price_cents' => Money::parse('99')]);
        $menu['cheese']->update(['price_cents' => Money::parse('50')]);
        $fresh = $order->fresh('items.addons');
        expect($fresh->items->first()->name)->not->toBe('Outro nome')
            ->and($fresh->items->first()->unit_price_cents->cents)->toBe(4000)
            ->and($fresh->items->first()->addons->first()->unit_price_cents->cents)->toBe(800)
            ->and($fresh->total_cents->cents)->toBe(15100);
    });
});

it('numera os pedidos a partir do 1 em cada estabelecimento', function () {
    $x = provisionTenant('numx');
    $y = provisionTenant('numy');
    $menuX = orderMenu($x);
    $menuY = orderMenu($y);

    expect(placeOnline($x, onlineOrderInput($menuX))->number)->toBe(1)
        ->and(placeOnline($x, onlineOrderInput($menuX))->number)->toBe(2)
        ->and(placeOnline($y, onlineOrderInput($menuY))->number)->toBe(1);
});

it('cobra a taxa de entrega e exige endereço', function () {
    $menu = orderMenu($this->a);
    inTenant($this->a, fn () => Setting::write('delivery_fee_cents', 700));

    expect(fn () => placeOnline($this->a, onlineOrderInput($menu, ['fulfillment' => 'delivery'])))
        ->toThrow(ValidationException::class);

    $order = placeOnline($this->a, onlineOrderInput($menu, [
        'fulfillment' => 'delivery',
        'address' => ['street' => 'Rua das Flores', 'number' => '10', 'district' => 'Centro'],
    ]));

    expect($order->fulfillment)->toBe(Fulfillment::Delivery)
        ->and($order->delivery_fee_cents->cents)->toBe(700)
        ->and($order->total_cents->cents)->toBe(15800)
        ->and(inTenant($this->a, fn () => Customer::where('phone', '11988887777')->first()->addresses()->count()))->toBeGreaterThan(0);

    inTenant($this->a, fn () => Setting::write('delivery_fee_cents', 0));
});

it('recusa pedidos online inválidos', function (Closure $breaks) {
    $menu = orderMenu($this->a);
    $input = $breaks($menu, $this);

    expect(fn () => placeOnline($this->a, $input))->toThrow(ValidationException::class);
})->with([
    'carrinho vazio' => fn ($m) => onlineOrderInput($m, ['lines' => [['type' => 'product', 'id' => 0, 'quantity' => 0]]]),
    'borda obrigatória faltando' => fn ($m) => onlineOrderInput($m, ['lines' => [['type' => 'product', 'id' => $m['pizza']->id, 'quantity' => 1, 'addons' => [$m['bacon']->id]]]]),
    'extras acima do máximo' => fn ($m) => onlineOrderInput($m, ['lines' => [['type' => 'product', 'id' => $m['pizza']->id, 'quantity' => 1, 'addons' => [$m['plain']->id, $m['bacon']->id, $m['egg']->id, $m['olive']->id]]]]),
    'adicional de outro grupo' => fn ($m) => onlineOrderInput($m, ['lines' => [['type' => 'product', 'id' => $m['pizza']->id, 'quantity' => 1, 'addons' => [$m['plain']->id, $m['sauce']->id]]]]),
    'adicional repetido' => fn ($m) => onlineOrderInput($m, ['lines' => [['type' => 'product', 'id' => $m['pizza']->id, 'quantity' => 1, 'addons' => [$m['plain']->id, $m['bacon']->id, $m['bacon']->id]]]]),
    'quantidade inválida' => fn ($m) => onlineOrderInput($m, ['lines' => [['type' => 'product', 'id' => $m['soda']->id, 'quantity' => 500]]]),
    'telefone inválido' => fn ($m) => onlineOrderInput($m, ['customer' => ['phone' => '1234']]),
    'sem nome' => fn ($m) => onlineOrderInput($m, ['customer' => ['name' => '']]),
    'pagamento inexistente' => fn ($m) => onlineOrderInput($m, ['payment_method' => 'bitcoin']),
    'troco menor que o total' => fn ($m) => onlineOrderInput($m, ['payment_method' => 'cash', 'change_for' => Money::parse('20')]),
    'balcão pelo site' => fn ($m) => onlineOrderInput($m, ['fulfillment' => 'counter']),
    'desconto pelo site' => fn ($m) => onlineOrderInput($m, ['discount' => Money::parse('5')]),
]);

it('não vende item esgotado, inativo ou excluído, nem combo com item indisponível', function () {
    $menu = orderMenu($this->a);
    $soda = ['lines' => [['type' => 'product', 'id' => $menu['soda']->id, 'quantity' => 1]]];
    $combo = ['lines' => [['type' => 'combo', 'id' => $menu['combo']->id, 'quantity' => 1]]];

    inTenant($this->a, fn () => $menu['soda']->update(['is_available' => false]));
    expect(fn () => placeOnline($this->a, onlineOrderInput($menu, $soda)))->toThrow(ValidationException::class, 'esgotado');
    expect(fn () => placeOnline($this->a, array_merge(onlineOrderInput($menu), $combo)))->toThrow(ValidationException::class);

    inTenant($this->a, fn () => $menu['soda']->update(['is_available' => true, 'is_active' => false]));
    expect(fn () => placeOnline($this->a, array_merge(onlineOrderInput($menu), $soda)))->toThrow(ValidationException::class);

    inTenant($this->a, fn () => $menu['soda']->update(['is_active' => true]));
    inTenant($this->a, fn () => $menu['soda']->delete());
    expect(fn () => placeOnline($this->a, array_merge(onlineOrderInput($menu), $soda)))->toThrow(ValidationException::class);
});

it('respeita horário, pedido mínimo e as opções de entrega do estabelecimento', function () {
    $tenant = provisionTenant('regras');
    $menu = orderMenu($tenant);

    $this->travelTo(CarbonImmutable::parse('2026-10-10 03:00', 'America/Sao_Paulo'));
    expect(fn () => placeOnline($tenant, onlineOrderInput($menu)))->toThrow(ValidationException::class, 'fechado');
    $this->travelTo(CarbonImmutable::parse('2026-10-09 15:00', 'America/Sao_Paulo'));

    inTenant($tenant, fn () => Setting::write('min_order_cents', 50000));
    expect(fn () => placeOnline($tenant, onlineOrderInput($menu)))->toThrow(ValidationException::class, 'mínimo');
    inTenant($tenant, fn () => Setting::write('min_order_cents', 0));

    inTenant($tenant, fn () => Setting::write('accepts_pickup', false));
    expect(fn () => placeOnline($tenant, onlineOrderInput($menu)))->toThrow(ValidationException::class);
    inTenant($tenant, fn () => Setting::write('accepts_pickup', true));

    inTenant($tenant, fn () => Setting::write('accepts_online_orders', false));
    expect(fn () => placeOnline($tenant, onlineOrderInput($menu)))->toThrow(ValidationException::class);
});

it('aceita o pedido pelo site nos dois formatos de endereço e mostra o acompanhamento', function () {
    $menu = orderMenu($this->a);
    $cart = json_encode([
        ['type' => 'product', 'id' => $menu['pizza']->id, 'quantity' => 1, 'addons' => [$menu['plain']->id]],
    ]);
    $form = ['cart' => $cart, 'name' => 'Bruno', 'phone' => '11977776666', 'fulfillment' => 'pickup', 'payment_method' => 'cash', 'change_for' => '50,00'];

    foreach ([tenantUrl($this->a, '/cardapio'), 'http://localhost/cardapio/teste1'] as $base) {
        $response = $this->post($base.'/pedido', $form);
        $order = inTenant($this->a, fn () => Order::latest('id')->first());

        $response->assertRedirect($base.'/pedido/'.$order->public_token.'?novo=1');
        $this->get($base.'/pedido/'.$order->public_token)
            ->assertOk()
            ->assertSee('#'.$order->number)
            ->assertSee('Pedido recebido')
            ->assertSee('R$ 40,00')
            ->assertSee('Troco para R$ 50,00');
    }

    // O token de A não existe em B.
    $this->get(tenantUrl($this->b, '/cardapio/pedido/'.$order->public_token))->assertNotFound();

    // Situação resumida (aba "Pedidos" do app e atualização automática).
    $this->getJson(tenantUrl($this->a, '/cardapio/pedido/'.$order->public_token.'/status'))
        ->assertOk()
        ->assertJson(['number' => $order->number, 'status' => 'received', 'final' => false, 'total' => 'R$ 40,00']);
    $this->getJson(tenantUrl($this->b, '/cardapio/pedido/'.$order->public_token.'/status'))->assertNotFound();
});

it('oferece o manifesto para instalar o cardápio como app', function () {
    foreach ([tenantUrl($this->a, '/cardapio'), 'http://localhost/cardapio/teste1'] as $base) {
        $this->get($base.'/manifest.webmanifest')
            ->assertOk()
            ->assertJson(['start_url' => $base, 'display' => 'standalone']);
    }
});

it('volta para o checkout com o erro quando o pedido é recusado', function () {
    $menu = orderMenu($this->a);
    inTenant($this->a, fn () => $menu['soda']->update(['is_available' => false]));
    $cart = json_encode([['type' => 'product', 'id' => $menu['soda']->id, 'quantity' => 1]]);

    $this->post(tenantUrl($this->a, '/cardapio/pedido'), ['cart' => $cart, 'name' => 'C', 'phone' => '11977776666', 'fulfillment' => 'pickup', 'payment_method' => 'pix'])
        ->assertRedirect(tenantUrl($this->a, '/cardapio/pedido'))
        ->assertSessionHasErrors();
});

it('mostra o carrinho no cardápio só quando aceita pedidos', function () {
    orderMenu($this->a);
    $this->get(tenantUrl($this->a, '/cardapio'))
        ->assertSee('Ver carrinho')
        ->assertSee('picker(', false)
        // Os botões "Adicionar" (combos, folha do produto) ficam dentro do escopo Alpine do app.
        ->assertSee('x-data="menuApp()"', false)
        ->assertSee('window.menuCatalog', false);

    inTenant($this->a, fn () => OpeningHour::query()->update(['is_closed' => true]));
    $this->get(tenantUrl($this->a, '/cardapio'))->assertDontSee('Ver carrinho')->assertSee('Estamos fechados agora');
});

it('segue o fluxo de status com as permissões de cada perfil', function () {
    $menu = orderMenu($this->a);
    $order = placeOnline($this->a, onlineOrderInput($menu));
    $cozinha = createTenantUser($this->a, ['cozinha']);
    $entregador = createTenantUser($this->a, ['entregador']);
    $caixa = createTenantUser($this->a, ['caixa']);
    $gerente = createTenantUser($this->a, ['gerente']);
    activateTenant($this->a);

    Livewire::actingAs($entregador)->test(Board::class)->call('advance', $order->id)->assertForbidden();
    Livewire::actingAs($cozinha)->test(Board::class)->call('advance', $order->id)->assertHasNoErrors();
    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);

    Livewire::actingAs($caixa)->test(Board::class)->call('startCancel', $order->id)->assertForbidden();

    Livewire::actingAs($cozinha)->test(Board::class)->call('advance', $order->id); // pronto
    Livewire::actingAs($cozinha)->test(Board::class)->call('advance', $order->id); // retirada → concluído

    $order->refresh()->load('payments', 'history');
    expect($order->status)->toBe(OrderStatus::Completed)
        ->and($order->completed_at)->not->toBeNull()
        ->and($order->payments->first()->status)->toBe(PaymentStatus::Paid)
        ->and($order->history->pluck('to_status')->map->value->all())->toBe(['received', 'preparing', 'ready', 'completed'])
        ->and($order->history->last()->user_id)->toBe($cozinha->id);

    // Concluído não volta nem cancela.
    expect(fn () => app(ChangeOrderStatus::class)->handle($order, OrderStatus::Cancelled, $gerente, 'teste'))
        ->toThrow(ValidationException::class);
});

it('cancela com motivo e registra na auditoria', function () {
    $menu = orderMenu($this->a);
    $order = placeOnline($this->a, onlineOrderInput($menu, ['fulfillment' => 'delivery', 'address' => ['street' => 'Rua A', 'number' => '1', 'district' => 'B']]));
    $gerente = createTenantUser($this->a, ['gerente']);
    activateTenant($this->a);

    Livewire::actingAs($gerente)->test(Show::class, ['order' => $order])
        ->set('cancelling', true)
        ->call('cancel')
        ->assertHasErrors(['cancelReason']);

    Livewire::actingAs($gerente)->test(Show::class, ['order' => $order])
        ->set('cancelReason', 'Cliente desistiu')
        ->call('cancel')
        ->assertHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->cancel_reason)->toBe('Cliente desistiu')
        ->and(App\Modules\Access\Models\AuditLog::where('action', 'order.cancelled')->where('subject_id', $order->id)->exists())->toBeTrue();
});

it('entrega passa por "saiu para entrega" antes de concluir', function () {
    expect(OrderStatus::Ready->next(Fulfillment::Delivery))->toBe(OrderStatus::Dispatched)
        ->and(OrderStatus::Ready->next(Fulfillment::Pickup))->toBe(OrderStatus::Completed)
        ->and(OrderStatus::Received->canTransitionTo(OrderStatus::Completed, Fulfillment::Pickup))->toBeFalse()
        ->and(OrderStatus::Preparing->canTransitionTo(OrderStatus::Cancelled, Fulfillment::Pickup))->toBeTrue();
});

it('vende no balcão com adicionais, pagamento na hora e desconto só com permissão', function () {
    $menu = orderMenu($this->a);
    $caixa = createTenantUser($this->a, ['caixa']);
    $gerente = createTenantUser($this->a, ['gerente']);
    activateTenant($this->a);
    // Venda de balcão é paga na hora: precisa do caixa aberto de cada operador.
    $ledger = app(App\Modules\Cash\CashLedger::class);
    $ledger->open(App\Modules\Cash\Models\CashRegister::create(['name' => 'Caixa '.Str::random(5)]), $caixa, Money::zero());
    $ledger->open(App\Modules\Cash\Models\CashRegister::create(['name' => 'Caixa '.Str::random(5)]), $gerente, Money::zero());

    $counter = Livewire::actingAs($caixa)->test(Counter::class)
        ->call('addProduct', $menu['pizza']->id)
        ->assertSet('pickingId', $menu['pizza']->id)
        ->call('confirmPick')
        ->assertHasErrors(['picked'])
        ->call('togglePick', $menu['crust']->id, $menu['cheese']->id)
        ->call('confirmPick')
        ->assertSet('pickingId', null)
        ->call('addProduct', $menu['soda']->id)
        ->call('addProduct', $menu['soda']->id)
        ->set('paymentMethod', 'cash');

    expect($counter->get('cart'))->toHaveCount(2);

    // Caixa não tem orders.discount.
    $counter->set('discount', '5')->call('checkout')->assertHasErrors(['discount']);
    $counter->set('discount', '')->call('checkout')->assertHasNoErrors();

    $order = Order::latest('id')->with('payments')->first();
    expect($order->source)->toBe(OrderSource::Counter)
        ->and($order->fulfillment)->toBe(Fulfillment::Counter)
        ->and($order->total_cents->cents)->toBe(4800 + 2000)
        ->and($order->created_by)->toBe($caixa->id)
        ->and($order->payments->first()->status)->toBe(PaymentStatus::Paid);

    Livewire::actingAs($gerente)->test(Counter::class)
        ->call('addProduct', $menu['soda']->id)
        ->set('discount', '2,50')
        ->call('checkout')
        ->assertHasNoErrors();

    expect(Order::latest('id')->first()->total_cents->cents)->toBe(750);
});

it('libera painel, balcão e histórico conforme o perfil', function () {
    $entregador = createTenantUser($this->a, ['entregador']);
    $cozinha = createTenantUser($this->a, ['cozinha']);
    $caixa = createTenantUser($this->a, ['caixa']);

    $this->flushSession();
    $this->actingAs($entregador)->get(tenantUrl($this->a, '/pedidos'))->assertOk();
    $this->flushSession();
    $this->actingAs($cozinha)->get(tenantUrl($this->a, '/balcao'))->assertForbidden();
    $this->flushSession();
    $this->actingAs($caixa)->get(tenantUrl($this->a, '/balcao'))->assertOk();
    $this->flushSession();
    $this->actingAs($caixa)->get(tenantUrl($this->a, '/pedidos/historico'))->assertOk();
});

it('não mostra no painel de B os pedidos de A', function () {
    $menu = orderMenu($this->a);
    $order = placeOnline($this->a, onlineOrderInput($menu, ['customer' => ['name' => 'Cliente Exclusivo A']]));
    $ownerB = createTenantUser($this->b);

    $this->actingAs($ownerB)->get(tenantUrl($this->b, '/pedidos'))->assertOk()->assertDontSee('Cliente Exclusivo A');
    $this->flushSession();
    $this->actingAs($ownerB)->get(tenantUrl($this->b, '/pedidos/'.$order->id))->assertNotFound();
});

it('reaproveita o cliente pelo telefone', function () {
    $menu = orderMenu($this->a);
    placeOnline($this->a, onlineOrderInput($menu, ['customer' => ['name' => 'Nome Antigo', 'phone' => '11955554444']]));
    placeOnline($this->a, onlineOrderInput($menu, ['customer' => ['name' => 'Nome Novo', 'phone' => '+55 (11) 95555-4444']]));

    inTenant($this->a, function () {
        expect(Customer::where('phone', '11955554444')->count())->toBe(1)
            ->and(Customer::where('phone', '11955554444')->value('name'))->toBe('Nome Novo')
            ->and(Order::where('customer_phone', '11955554444')->count())->toBe(2);
    });
});
