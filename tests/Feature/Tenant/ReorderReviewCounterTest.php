<?php

use App\Livewire\Tenant\Orders\Counter;
use App\Livewire\Tenant\Reports\Index as ReportsIndex;
use App\Livewire\Tenant\Tables\CommandPage;
use App\Modules\Delivery\Actions\Deliveries;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReview;
use App\Modules\Promotions\CouponType;
use App\Modules\Promotions\Models\Coupon;
use App\Modules\Push\StaffAlerts;
use App\Modules\Push\Transports\FakePushTransport;
use App\Modules\Tables\Actions\ManageCommand;
use App\Support\Money;
use Livewire\Livewire;

beforeEach(fn () => FakePushTransport::reset());

/** Pedido do cardápio já entregue (retirada concluída). */
function completedOrder(array $menu): Order
{
    $order = app(PlaceOrder::class)->handle(onlineOrderInput($menu), OrderSource::Online);

    foreach ([OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Completed] as $status) {
        app(ChangeOrderStatus::class)->handle($order, $status);
    }

    return $order->fresh();
}

it('o PDV guarda o pedido em andamento e recupera depois de atualizar a página', function () {
    ['tenant' => $tenant, 'burger' => $burger, 'soda' => $soda] = salon();
    $caixa = createTenantUser($tenant, ['caixa']);

    Livewire::actingAs($caixa)->test(Counter::class)
        ->call('addProduct', $burger->id)->call('addProduct', $burger->id)->call('addProduct', $soda->id)
        ->set('fulfillment', 'delivery')->set('customerName', 'Rita')->set('customerPhone', '11977775555')
        ->set('address.street', 'Rua A')->set('notes', 'sem cebola');

    // "Atualizou a página": a tela abre de novo com tudo.
    $page = Livewire::actingAs($caixa)->test(Counter::class)
        ->assertSet('draftRestored', true)->assertSee('Recuperamos o pedido que estava em andamento.')
        ->assertSet('fulfillment', 'delivery')->assertSet('customerName', 'Rita')->assertSet('address.street', 'Rua A')->assertSet('notes', 'sem cebola');
    expect(collect($page->get('cart'))->sum('quantity'))->toBe(3);

    // "Começar do zero" apaga o rascunho.
    $page->call('clearCart');
    Livewire::actingAs($caixa)->test(Counter::class)->assertSet('cart', [])->assertSet('draftRestored', false)->assertDontSee('Recuperamos o pedido');

    // Pedido finalizado também não volta.
    Livewire::actingAs($caixa)->test(Counter::class)
        ->call('addProduct', $burger->id)->set('fulfillment', 'pickup')->set('customerName', 'Rita')
        ->call('checkout')->assertHasNoErrors();
    expect(Order::count())->toBe(1);
    Livewire::actingAs($caixa)->test(Counter::class)->assertSet('cart', [])->assertSet('customerName', '');
});

it('a comanda guarda os itens ainda não enviados, separados por mesa', function () {
    ['tenant' => $tenant, 'burger' => $burger, 't1' => $t1, 't2' => $t2] = salon();
    $garcom = createTenantUser($tenant, ['garcom']);
    $manage = app(ManageCommand::class);
    $mesa1 = $manage->open($t1, null, 2, $garcom);
    $mesa2 = $manage->open($t2, null, 2, $garcom);

    Livewire::actingAs($garcom)->test(CommandPage::class, ['command' => $mesa1])->call('addProduct', $burger->id)->set('sendNotes', 'bem passado');

    Livewire::actingAs($garcom)->test(CommandPage::class, ['command' => $mesa1])
        ->assertSet('adding', true)->assertSet('sendNotes', 'bem passado')->assertSee('Recuperamos os itens que ainda não foram enviados à cozinha.')
        ->call('send')->assertHasNoErrors();
    Livewire::actingAs($garcom)->test(CommandPage::class, ['command' => $mesa2])->assertSet('cart', []);
    Livewire::actingAs($garcom)->test(CommandPage::class, ['command' => $mesa1])->assertSet('cart', [])->assertDontSee('Recuperamos os itens');
});

it('o PDV aceita cupom no lugar do desconto manual', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    $caixa = createTenantUser($tenant, ['caixa']);
    Coupon::create(['code' => 'BALCAO10', 'type' => CouponType::Percent, 'percent' => 10]);
    Coupon::create(['code' => 'CLIENTE', 'type' => CouponType::Fixed, 'amount_cents' => Money::parse('5'), 'per_customer_limit' => 1]);

    $pdv = Livewire::actingAs($caixa)->test(Counter::class)
        ->call('addProduct', $burger->id)->call('addProduct', $burger->id)->set('fulfillment', 'pickup')
        ->set('couponInput', 'naoexiste')->call('applyCoupon')->assertHasErrors('coupon')
        ->set('couponInput', 'balcao10')->call('applyCoupon')->assertHasNoErrors()
        ->assertSet('coupon', 'BALCAO10')->assertSee('10% de desconto')->assertSee('- R$ 6,00')->assertSee('R$ 54,00');

    $pdv->call('checkout')->assertHasNoErrors();
    $order = Order::sole();
    expect($order->coupon_code)->toBe('BALCAO10')->and($order->discount_cents->format())->toBe('R$ 6,00')->and($order->source)->toBe(OrderSource::Counter);

    // Cupom com limite por cliente precisa do telefone.
    Livewire::actingAs($caixa)->test(Counter::class)
        ->call('addProduct', $burger->id)->set('fulfillment', 'pickup')
        ->set('couponInput', 'CLIENTE')->call('applyCoupon')
        ->assertSee('Informe o telefone do cliente para usar este cupom.')
        ->call('checkout')->assertHasErrors(['coupon' => 'Informe o telefone do cliente para usar este cupom.'])
        ->set('customerPhone', '11977775555')->call('checkout')->assertHasNoErrors();

    // Desconto manual e cupom juntos, não.
    $gerente = createTenantUser($tenant, ['gerente']);
    Livewire::actingAs($gerente)->test(Counter::class)
        ->call('addProduct', $burger->id)->set('fulfillment', 'pickup')->set('discount', '3')
        ->set('couponInput', 'BALCAO10')->call('applyCoupon')->assertHasErrors(['coupon' => 'Use o cupom ou o desconto manual, não os dois.']);
});

it('pedir de novo monta o carrinho com o cardápio de hoje e avisa o que saiu', function () {
    ['tenant' => $tenant] = salon();
    $menu = orderMenu($tenant);
    activateTenant($tenant);
    $order = completedOrder($menu);
    $reorder = fn () => asCustomer($tenant, fn () => $this->getJson(tenantUrl($tenant, "/cardapio/pedido/{$order->public_token}/repetir"))->assertOk());

    $menu['pizza']->update(['price_cents' => Money::parse('45')]); // preço mudou: vale o de hoje
    $data = $reorder()->json();
    expect($data['skipped'])->toBe([])
        ->and($data['lines'][0])->toMatchArray(['type' => 'product', 'id' => $menu['pizza']->id, 'unit' => 4500, 'quantity' => 2, 'notes' => 'sem cebola'])
        ->and(array_column($data['lines'][0]['addons'], 'name'))->toBe(['Catupiry', 'Bacon'])
        ->and($data['lines'][1])->toMatchArray(['type' => 'combo', 'id' => $menu['combo']->id, 'quantity' => 1]);

    // Adicional que saiu e produto esgotado: ficam de fora, com o nome.
    $menu['bacon']->update(['is_active' => false]);
    $menu['soda']->update(['is_available' => false]); // o combo leva o refrigerante
    $data = $reorder()->json();
    expect($data['lines'])->toBe([])->and($data['skipped'])->toHaveCount(2);

    asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, "/cardapio/pedido/{$order->public_token}")))->assertOk()->assertSee('Pedir de novo');
});

it('o cliente avalia o pedido entregue uma vez; nota ruim avisa a gerência', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    $ana = createTenantUser($tenant, ['entregador'], ['name' => 'Ana Souza']);
    $gerente = createTenantUser($tenant, ['gerente']);
    phoneOf($gerente);
    $order = readyDeliveryOrder($burger);
    $url = tenantUrl($tenant, "/cardapio/pedido/{$order->public_token}/avaliacao");
    $review = fn (array $body) => asCustomer($tenant, fn () => $this->postJson($url, $body));
    $page = fn () => asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, "/cardapio/pedido/{$order->public_token}"))->assertOk());

    $review(['rating' => 5])->assertStatus(422)->assertJson(['message' => 'Dá para avaliar depois que o pedido for entregue.']);
    $page()->assertDontSee('Como foi seu pedido?');

    app(Deliveries::class)->take($order->fresh(), $ana);
    app(Deliveries::class)->complete($order->fresh(), $ana);
    $page()->assertSee('Como foi seu pedido?');
    FakePushTransport::reset();

    $review(['rating' => 2, 'tags' => ['late', 'cold', 'fast', 'hack'], 'comment' => 'Chegou <b>frio</b> e atrasado'])->assertCreated();
    $saved = OrderReview::sole();
    expect($saved->only(['rating', 'tags', 'comment', 'courier_id']))->toBe(['rating' => 2, 'tags' => ['late', 'cold'], 'comment' => 'Chegou frio e atrasado', 'courier_id' => $ana->id]);
    expect(pushesTo($gerente)[0]['payload'])->toMatchArray([
        'title' => "Avaliação ruim · #{$order->number} · ★★☆☆☆",
        'body' => 'Rita · Demorou, Chegou frio · “Chegou frio e atrasado”',
        'url' => "/pedidos/{$order->id}",
    ]);

    $review(['rating' => 5])->assertStatus(422)->assertJson(['message' => 'Este pedido já foi avaliado. Obrigado!']);
    $page()->assertSee('Sua avaliação')->assertSee('★★☆☆☆')->assertSee('Chegou frio')->assertDontSee('Como foi seu pedido?');

    // Nota boa não avisa; depois de 7 dias não dá mais para avaliar.
    $boa = readyDeliveryOrder($burger);
    app(Deliveries::class)->take($boa->fresh(), $ana);
    app(Deliveries::class)->complete($boa->fresh(), $ana);
    FakePushTransport::reset(); // (o aviso de pedido novo deste pedido não interessa aqui)
    asCustomer($tenant, fn () => $this->postJson(tenantUrl($tenant, "/cardapio/pedido/{$boa->public_token}/avaliacao"), ['rating' => 5, 'tags' => ['fast']]))->assertCreated();
    expect(FakePushTransport::$sent)->toBeEmpty();

    $velho = readyDeliveryOrder($burger);
    app(Deliveries::class)->take($velho->fresh(), $ana);
    app(Deliveries::class)->complete($velho->fresh(), $ana);
    $this->travel(8)->days();
    asCustomer($tenant, fn () => $this->postJson(tenantUrl($tenant, "/cardapio/pedido/{$velho->public_token}/avaliacao"), ['rating' => 4]))
        ->assertStatus(422)->assertJson(['message' => 'O prazo para avaliar este pedido terminou.']);
    $this->travelBack();

    // A gerência pode desligar o aviso; relatório e detalhe do pedido mostram as avaliações.
    expect(StaffAlerts::choicesFor($gerente))->toHaveKey(StaffAlerts::BAD_REVIEW);
    $today = now()->timezone('America/Sao_Paulo')->toDateString();
    Livewire::actingAs($gerente)->test(ReportsIndex::class, ['from' => $today, 'to' => $today])->set('tab', 'reviews')
        ->assertSee('3,5 ★')->assertSee('50%')->assertSee('Demorou')->assertSee('Ana Souza')->assertSee('“Chegou frio e atrasado”');
    $this->actingAs($gerente)->get(tenantUrl($tenant, "/pedidos/{$order->id}"))->assertOk()->assertSee('Avaliação do cliente')->assertSee('★★☆☆☆');
});
