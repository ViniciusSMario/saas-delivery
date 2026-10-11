<?php

use App\Livewire\Tenant\Promotions\Coupons;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Promotions\CouponType;
use App\Modules\Promotions\Models\Coupon;
use App\Modules\Promotions\Models\CouponRedemption;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** Pedido do cardápio com cupom (hambúrguer de R$ 30 na quantidade pedida). */
function couponOrder(Product $product, ?string $coupon, int $quantity = 2, string $fulfillment = 'delivery', string $phone = '11977775555'): Order
{
    return app(PlaceOrder::class)->handle([
        'lines' => [line($product, $quantity)],
        'fulfillment' => $fulfillment,
        'payment_method' => 'cash',
        'customer' => ['name' => 'Rita', 'phone' => $phone],
        'address' => ['street' => 'Rua A', 'number' => '10', 'district' => 'Centro'],
        'coupon' => $coupon,
    ], OrderSource::Online);
}

function makeCoupon(array $attributes = []): Coupon
{
    return Coupon::create($attributes + ['code' => 'PROMO10', 'type' => CouponType::Percent, 'percent' => 10]);
}

/** Mensagem do erro de cupom ao tentar fazer o pedido. */
function couponError(Closure $place): ?string
{
    try {
        $place();
    } catch (ValidationException $e) {
        return $e->errors()['coupon'][0] ?? null;
    }

    return null;
}

it('aplica porcentagem com teto, valor fixo e entrega grátis sobre o pedido', function () {
    ['burger' => $burger] = salon();
    Setting::write('delivery_fee_cents', 700);

    makeCoupon(['code' => 'DEZ', 'percent' => 10, 'max_discount_cents' => Money::parse('5')]);
    $order = couponOrder($burger, ' dez ', 2); // R$ 60 de itens; 10% = R$ 6, mas o teto é R$ 5
    expect($order->discount_cents->format())->toBe('R$ 5,00')
        ->and($order->total_cents->format())->toBe('R$ 62,00') // 60 − 5 + 7 de entrega
        ->and($order->coupon_code)->toBe('DEZ')
        ->and(CouponRedemption::sole()->only(['order_id', 'customer_phone']))->toBe(['order_id' => $order->id, 'customer_phone' => '11977775555'])
        ->and(CouponRedemption::sole()->discount_cents->cents)->toBe(500);

    makeCoupon(['code' => 'MENOS50', 'type' => CouponType::Fixed, 'percent' => null, 'amount_cents' => Money::parse('50'), 'per_customer_limit' => null]);
    expect(couponOrder($burger, 'MENOS50', 1, phone: '11966665555')->discount_cents->format())->toBe('R$ 30,00'); // nunca mais que os itens

    makeCoupon(['code' => 'FRETE', 'type' => CouponType::FreeDelivery, 'percent' => null]);
    $free = couponOrder($burger, 'FRETE', 1, phone: '11955554444');
    expect($free->discount_cents->format())->toBe('R$ 7,00')
        ->and($free->delivery_fee_cents->format())->toBe('R$ 7,00') // o entregador continua com a taxa
        ->and($free->total_cents->format())->toBe('R$ 30,00');
    expect(couponError(fn () => couponOrder($burger, 'FRETE', 1, 'pickup', '11944443333')))->toBe('Este cupom é de entrega grátis: escolha entrega para usar.');
});

it('recusa cupom inexistente, pausado, fora do prazo ou abaixo do mínimo', function () {
    ['burger' => $burger] = salon();

    expect(couponError(fn () => couponOrder($burger, 'NAOEXISTE')))->toBe('Cupom não encontrado. Confira o código.');

    makeCoupon(['code' => 'PAUSADO', 'is_active' => false]);
    expect(couponError(fn () => couponOrder($burger, 'PAUSADO')))->toBe('Este cupom não está mais ativo.');

    makeCoupon(['code' => 'FUTURO', 'starts_at' => now()->addDays(2)]);
    expect(couponError(fn () => couponOrder($burger, 'FUTURO')))->toStartWith('Este cupom vale a partir de');

    makeCoupon(['code' => 'VENCIDO', 'ends_at' => now()->subMinute()]);
    expect(couponError(fn () => couponOrder($burger, 'VENCIDO')))->toBe('Este cupom expirou.');

    makeCoupon(['code' => 'MINIMO', 'min_order_cents' => Money::parse('100')]);
    expect(couponError(fn () => couponOrder($burger, 'MINIMO', 2)))->toBe('Este cupom vale para pedidos a partir de R$ 100,00 (sem a taxa de entrega).');

    // Nada foi gravado nas tentativas recusadas.
    expect(Order::count())->toBe(0)->and(CouponRedemption::count())->toBe(0);

    // Cupom é só do cardápio online: o balcão usa o desconto manual.
    expect(couponError(fn () => app(PlaceOrder::class)->handle([
        'lines' => [line($burger)], 'fulfillment' => 'pickup', 'payment_method' => 'cash',
        'customer' => ['name' => 'Rita', 'phone' => '11977775555'], 'coupon' => 'MINIMO',
    ], OrderSource::Counter)))->toBe('Cupom só vale no cardápio online.');
});

it('controla usos no total, por cliente e primeiro pedido; cancelar devolve o uso', function () {
    ['burger' => $burger] = salon();

    makeCoupon(['code' => 'UNICO', 'usage_limit' => 1]);
    $first = couponOrder($burger, 'UNICO', phone: '11911112222');
    expect(couponError(fn () => couponOrder($burger, 'UNICO', phone: '11933334444')))->toBe('Este cupom já esgotou.');
    app(ChangeOrderStatus::class)->handle($first, OrderStatus::Cancelled, reason: 'cliente desistiu');
    expect(couponOrder($burger, 'UNICO', phone: '11933334444')->coupon_code)->toBe('UNICO');

    makeCoupon(['code' => 'UMAVEZ', 'per_customer_limit' => 1]);
    couponOrder($burger, 'UMAVEZ', phone: '11955556666');
    expect(couponError(fn () => couponOrder($burger, 'UMAVEZ', phone: '(11) 95555-6666')))->toBe('Você já usou este cupom.');
    expect(couponOrder($burger, 'UMAVEZ', phone: '11977778888')->coupon_code)->toBe('UMAVEZ');

    makeCoupon(['code' => 'PRIMEIRA', 'first_order_only' => true]);
    expect(couponError(fn () => couponOrder($burger, 'PRIMEIRA', phone: '11977778888')))->toBe('Este cupom é só para o primeiro pedido.');
    expect(couponOrder($burger, 'PRIMEIRA', phone: '11999990000')->coupon_code)->toBe('PRIMEIRA');
});

it('o checkout confere o cupom na hora e manda o código junto com o pedido', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    makeCoupon(['code' => 'BEMVINDO', 'percent' => 15, 'min_order_cents' => Money::parse('40'), 'first_order_only' => true]);
    $preview = fn (array $body) => asCustomer($tenant, fn () => $this->postJson(tenantUrl($tenant, '/cardapio/pedido/cupom'), $body));

    $preview(['code' => 'bemvindo', 'subtotal' => 6000, 'fulfillment' => 'delivery', 'phone' => '11977775555'])
        ->assertOk()->assertJson(['code' => 'BEMVINDO', 'type' => 'percent', 'percent' => 15, 'min' => 4000, 'benefit' => '15% de desconto']);
    $preview(['code' => 'BEMVINDO', 'subtotal' => 3000])->assertStatus(422)->assertJson(['message' => 'Este cupom vale para pedidos a partir de R$ 40,00 (sem a taxa de entrega).']);
    $preview(['code' => 'XYZ', 'subtotal' => 6000])->assertStatus(422)->assertJson(['message' => 'Cupom não encontrado. Confira o código.']);

    $form = [
        'cart' => json_encode([['type' => 'product', 'id' => $burger->id, 'quantity' => 2]]),
        'fulfillment' => 'pickup', 'payment_method' => 'cash', 'name' => 'Rita', 'phone' => '11977775555', 'coupon' => 'BEMVINDO',
    ];
    asCustomer($tenant, fn () => $this->post(tenantUrl($tenant, '/cardapio/pedido'), $form))->assertRedirect();
    $order = Order::sole();
    expect($order->coupon_code)->toBe('BEMVINDO')->and($order->discount_cents->format())->toBe('R$ 9,00');

    asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, "/cardapio/pedido/{$order->public_token}")))
        ->assertOk()->assertSee('Cupom BEMVINDO')->assertSee('- R$ 9,00');

    // Segunda vez o mesmo cliente: volta para o checkout com o motivo.
    asCustomer($tenant, fn () => $this->post(tenantUrl($tenant, '/cardapio/pedido'), $form))
        ->assertRedirect()->assertSessionHasErrors(['coupon' => 'Este cupom é só para o primeiro pedido.']);
    expect(Order::count())->toBe(1);
});

it('o cardápio divulga cupons e o pedido entregue oferece o volte e ganhe', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    makeCoupon(['code' => 'INSTA20', 'percent' => 20, 'is_public' => true, 'ends_at' => now()->addDays(3)]);
    makeCoupon(['code' => 'ESCONDIDO', 'percent' => 50]);
    makeCoupon(['code' => 'VOLTE10', 'percent' => 10, 'show_after_order' => true, 'per_customer_limit' => 1]);

    asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, '/cardapio')))->assertOk()
        ->assertSee('INSTA20')->assertSee('20% de desconto')->assertSee('válido até')->assertDontSee('ESCONDIDO')->assertDontSee('VOLTE10');

    $order = couponOrder($burger, null, fulfillment: 'pickup');
    $page = fn () => asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, "/cardapio/pedido/{$order->public_token}"))->assertOk());
    $page()->assertDontSee('Volte e ganhe'); // ainda não foi entregue

    foreach ([OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Completed] as $status) {
        app(ChangeOrderStatus::class)->handle($order, $status);
    }
    $page()->assertSee('Volte e ganhe')->assertSee('VOLTE10')->assertSee('10% de desconto no próximo pedido');

    // Quem já usou o "volte e ganhe" não vê de novo.
    $again = couponOrder($burger, 'VOLTE10', fulfillment: 'pickup');
    foreach ([OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Completed] as $status) {
        app(ChangeOrderStatus::class)->handle($again, $status);
    }
    asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, "/cardapio/pedido/{$again->public_token}")))->assertOk()->assertDontSee('Volte e ganhe');
});

it('a gerência cria, edita, pausa e exclui cupons e vê o resultado de cada um', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    $gerente = createTenantUser($tenant, ['gerente']);
    $caixa = createTenantUser($tenant, ['caixa']);

    $page = Livewire::actingAs($gerente)->test(Coupons::class)
        ->call('create')->assertSet('showForm', true)
        ->set('code', 'primeira 10')->set('type', 'percent')->set('percent', '10')->set('maxDiscount', '20')
        ->set('minOrder', '40')->set('endsAt', now()->addMonth()->toDateString())->set('firstOrderOnly', true)->set('isPublic', true)
        ->call('save')->assertHasNoErrors();

    $coupon = Coupon::sole();
    expect($coupon->code)->toBe('PRIMEIRA10')
        ->and($coupon->max_discount_cents->format())->toBe('R$ 20,00')
        ->and($coupon->min_order_cents->format())->toBe('R$ 40,00')
        ->and($coupon->ends_at->timezone('America/Sao_Paulo')->format('H:i'))->toBe('23:59') // vale até o fim do dia
        ->and($coupon->per_customer_limit)->toBe(1);

    // Código repetido e valor obrigatório.
    $page->call('create')->set('code', 'PRIMEIRA10')->call('save')->assertHasErrors('code');
    $page->call('create')->set('code', 'FIXO')->set('type', 'fixed')->set('amount', '')->call('save')->assertHasErrors('amount');

    // Resultado: usos, desconto e vendas (cancelado não conta).
    couponOrder($burger, 'PRIMEIRA10', 2, 'pickup', '11911110000');
    $cancelled = couponOrder($burger, 'PRIMEIRA10', 2, 'pickup', '11922220000');
    app(ChangeOrderStatus::class)->handle($cancelled, OrderStatus::Cancelled, reason: 'teste');
    Livewire::actingAs($gerente)->test(Coupons::class)
        ->assertSee('PRIMEIRA10')->assertSee('10% de desconto (até R$ 20,00)')->assertSee('R$ 6,00')->assertSee('R$ 54,00');

    $page->call('edit', $coupon->id)->assertSet('percent', '10')->set('percent', '15')->call('save')->assertHasNoErrors();
    expect($coupon->fresh()->percent)->toBe(15);

    $page->call('toggle', $coupon->id);
    expect($coupon->fresh()->is_active)->toBeFalse();

    // Excluir libera o código; o pedido continua com ele.
    $page->call('delete', $coupon->id);
    expect(Coupon::count())->toBe(0)->and(Order::first()->coupon_code)->toBe('PRIMEIRA10');
    makeCoupon(['code' => 'PRIMEIRA10']);

    // Quem não gerencia o cardápio não acessa.
    $this->actingAs($caixa)->get(tenantUrl($tenant, '/cupons'))->assertForbidden();
});
