<?php

use App\Livewire\Tenant\Customers\Winback as WinbackPage;
use App\Livewire\Tenant\Orders\Board;
use App\Livewire\Tenant\Orders\RushControl;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerWinback;
use App\Modules\Customers\Winback;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\OrderEta;
use App\Modules\Orders\RushMode;
use App\Modules\Promotions\CouponType;
use App\Modules\Promotions\Models\Coupon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** Pedido do cardápio (retirada, ou entrega quando pedido) feito há alguns dias. */
function rushOrder(Product $product, string $phone = '11977775555', string $name = 'Rita Moura', int $daysAgo = 0, int $quantity = 1, string $fulfillment = 'pickup'): Order
{
    $order = app(PlaceOrder::class)->handle([
        'lines' => [line($product, $quantity)],
        'fulfillment' => $fulfillment,
        'payment_method' => 'cash',
        'customer' => ['name' => $name, 'phone' => $phone],
        'address' => ['street' => 'Rua A', 'number' => '10', 'district' => 'Centro'],
    ], OrderSource::Online);

    if ($daysAgo) {
        Order::whereKey($order->id)->update(['created_at' => now()->subDays($daysAgo)]);
    }

    return $order->fresh();
}

function placeError(Closure $place): ?string
{
    try {
        $place();
    } catch (ValidationException $e) {
        return collect($e->errors())->flatten()->first();
    }

    return null;
}

// ---------- Hora do sufoco ----------

it('aumentar a previsão vale para quem pede agora e não muda pedidos antigos', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    $before = rushOrder($burger, fulfillment: 'delivery');

    RushMode::delay(30);
    expect(OrderEta::now(Fulfillment::Delivery))->toBe(75)->and(OrderEta::now(Fulfillment::Pickup))->toBe(50);

    $during = rushOrder($burger, '11966665555', fulfillment: 'delivery');
    expect($during->eta_extra_minutes)->toBe(30)
        ->and(OrderEta::for($during)->diffInMinutes($during->created_at, true))->toEqual(75)
        ->and(OrderEta::for($before)->diffInMinutes($before->created_at, true))->toEqual(45);

    $menu = asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, '/cardapio')))->assertOk();
    $menu->assertSee('Estamos com muitos pedidos agora')->assertSee('~75 min');

    // Voltar ao normal: pedido novo sem extra, mas o feito no pico mantém a previsão dele.
    RushMode::clearDelay();
    expect(RushMode::extraMinutes())->toBe(0)
        ->and(rushOrder($burger, '11955554444')->eta_extra_minutes)->toBe(0)
        ->and(OrderEta::for($during->fresh())->diffInMinutes($during->created_at, true))->toEqual(75);

    // Esquecido ligado: volta ao normal sozinho depois de algumas horas.
    RushMode::delay(15);
    $this->travel(RushMode::DELAY_HOURS)->hours();
    $this->travel(1)->minutes();
    expect(RushMode::extraMinutes())->toBe(0)->and(RushMode::delayUntil())->toBeNull();
});

it('pausar os pedidos online recusa o pedido, avisa no cardápio e volta sozinho', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    Setting::write('timezone', 'UTC');

    RushMode::pause(15);
    $until = now()->addMinutes(15)->format('H:i');

    expect(placeError(fn () => rushOrder($burger)))->toBe("Estamos com muitos pedidos e pausamos os pedidos online até as {$until}. Tente de novo em alguns minutos.")
        ->and(Order::count())->toBe(0);

    asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, '/cardapio')))
        ->assertSee("Voltamos às {$until}");
    asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, '/cardapio/pedido')))
        ->assertSee('pausamos os pedidos online por alguns minutos. Voltamos às '.$until);

    $this->travel(16)->minutes();
    expect(RushMode::pausedUntil())->toBeNull()->and(rushOrder($burger)->exists)->toBeTrue();

    // Retomar antes da hora.
    RushMode::pause(60);
    RushMode::resume();
    expect(rushOrder($burger, '11966665555')->exists)->toBeTrue();

    expect(fn () => RushMode::pause(7))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('a faixa do painel de pedidos liga e desliga a hora do sufoco, com auditoria', function () {
    ['tenant' => $tenant] = salon();
    $cozinha = createTenantUser($tenant, ['cozinha']);
    $entregador = createTenantUser($tenant, ['entregador']);

    Livewire::actingAs($cozinha)->test(Board::class)->assertSeeLivewire(RushControl::class);

    $this->actingAs($cozinha);
    Livewire::actingAs($cozinha)->test(RushControl::class)
        ->assertSee('Movimento normal')->assertSee('Previsão no cardápio: entrega ~45 min · retirada ~20 min.')
        ->call('delay', 30)->assertSee('Hora do sufoco: previsão +30 min')->assertSee('entrega ~75 min')
        ->call('pause', 15)->assertSee('Pedidos online pausados até')->assertSee('Retomar agora')
        ->call('resume')->assertDontSee('Pedidos online pausados')
        ->call('clearDelay')->assertSee('Movimento normal');

    expect(AuditLog::orderBy('id')->pluck('action')->filter(fn ($a) => str_starts_with($a, 'rush.'))->values()->all())
        ->toBe(['rush.delay', 'rush.paused', 'rush.resumed', 'rush.delay_cleared']);

    // Quem só vê pedidos não tem os botões nem consegue chamar a ação.
    $this->actingAs($entregador);
    Livewire::actingAs($entregador)->test(RushControl::class)->assertDontSee('Pausar online')->call('pause', 15)->assertForbidden();
    expect(RushMode::pausedUntil())->toBeNull();
});

// ---------- Clientes sumidos ----------

it('lista quem pedia e parou, com o que mais pedia, e esconde quem não deve ser chamado', function () {
    ['tenant' => $tenant, 'burger' => $burger, 'soda' => $soda] = salon();
    $dono = createTenantUser($tenant, ['dono']);

    rushOrder($burger, '11911112222', 'ANA LIMA', daysAgo: 70, quantity: 2);
    rushOrder($soda, '11911112222', 'ANA LIMA', daysAgo: 50);
    rushOrder($burger, '11911112222', 'ANA LIMA', daysAgo: 40);             // sumida há 40 dias, mais pedia hambúrguer
    rushOrder($soda, '11933334444', 'Bruno', daysAgo: 45);                   // só 1 pedido
    rushOrder($burger, '11955556666', 'Carla', daysAgo: 60);
    rushOrder($burger, '11955556666', 'Carla', daysAgo: 5);                  // pediu há pouco
    $cancelled = [rushOrder($burger, '11977778888', 'Davi', daysAgo: 80), rushOrder($burger, '11977778888', 'Davi', daysAgo: 70)];
    foreach ($cancelled as $order) {
        app(ChangeOrderStatus::class)->handle($order, OrderStatus::Cancelled, null, 'teste');
    }
    rushOrder($burger, '11999990000', 'Elisa', daysAgo: 90);
    rushOrder($burger, '11999990000', 'Elisa', daysAgo: 35);
    Customer::where('phone', '11999990000')->update(['winback_opt_out_at' => now()]); // pediu para não ser chamada

    $names = fn (int $days, int $min) => app(Winback::class)->query($days, $min)->pluck('name')->sort()->values()->all();
    expect($names(30, 2))->toBe(['ANA LIMA'])
        ->and($names(30, 1))->toBe(['ANA LIMA', 'Bruno'])
        ->and($names(42, 1))->toBe(['Bruno'])
        ->and(app(Winback::class)->favorites(Customer::pluck('id')->all())[Customer::where('name', 'ANA LIMA')->value('id')])->toBe('Hambúrguer');

    $this->actingAs($dono);
    Livewire::actingAs($dono)->test(WinbackPage::class)
        ->assertSee('ANA LIMA')->assertSee('Sem pedir há 40 dias')->assertSeeInOrder(['Mais pedia:', 'Hambúrguer'])
        ->assertDontSee('Carla')->assertDontSee('Davi')->assertDontSee('Elisa')->assertDontSee('Bruno')
        ->set('minOrders', 1)->assertSee('Bruno')
        ->call('optOut', Customer::where('name', 'Bruno')->value('id'))->assertDontSee('Bruno');
});

it('monta a mensagem com o cupom, registra o contato e mede quem voltou', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    $caixa = createTenantUser($tenant, ['caixa']);
    rushOrder($burger, '11911112222', 'ANA LIMA', daysAgo: 60);
    rushOrder($burger, '11911112222', 'ANA LIMA', daysAgo: 40);
    $ana = Customer::where('phone', '11911112222')->sole();

    $this->actingAs($caixa);
    $page = Livewire::actingAs($caixa)->test(WinbackPage::class);

    // A mensagem padrão cita o cupom: sem cupom escolhido, o botão fica desligado.
    $page->assertSee('A mensagem cita o cupom')->assertDontSee('https://wa.me/', false);

    $coupon = Coupon::create(['code' => 'SAUDADE', 'type' => CouponType::Percent, 'percent' => 15, 'per_customer_limit' => 1]);
    Coupon::create(['code' => 'PRIMEIRA', 'type' => CouponType::Percent, 'percent' => 10, 'first_order_only' => true]);
    $page = Livewire::actingAs($caixa)->test(WinbackPage::class)->assertDontSee('PRIMEIRA ·')->set('couponId', $coupon->id);
    expect(Setting::read(Winback::COUPON_SETTING))->toBe($coupon->id);

    $html = html_entity_decode($page->html());
    preg_match('#https://wa\.me/5511911112222\?text=([^"]+)#', $html, $match);
    $text = rawurldecode($match[1] ?? '');
    expect($text)->toContain('Oi, Ana!')->toContain('use o cupom SAUDADE e ganhe 15% de desconto')->toContain('/cardapio?cupom=SAUDADE');

    // Clicou em "Chamar no WhatsApp": registra e sai da lista (até poder chamar de novo).
    $page->call('contacted', $ana->id)->assertDontSee('ANA LIMA')
        ->set('showContacted', true)->assertSee('ANA LIMA')->assertSee('Chamado');
    expect(CustomerWinback::sole()->only(['customer_id', 'user_id', 'coupon_code']))->toBe(['customer_id' => $ana->id, 'user_id' => $caixa->id, 'coupon_code' => 'SAUDADE']);

    // Voltou e usou o cupom: entra no resultado, e o cupom aparece como já usado.
    $back = app(PlaceOrder::class)->handle([
        'lines' => [line($burger, 2)], 'fulfillment' => 'pickup', 'payment_method' => 'cash',
        'customer' => ['name' => 'Ana Lima', 'phone' => '11911112222'], 'coupon' => 'saudade',
    ], OrderSource::Online);
    expect(app(Winback::class)->results())->toBe(['contacted' => 1, 'returned' => 1, 'revenue_cents' => $back->total_cents->cents]);

    // A caixa escolhe cupom, mas não cria (cupons são do cardápio).
    Livewire::actingAs($caixa)->test(WinbackPage::class)->assertDontSee('Ou crie um cupom de volta agora')->call('createCoupon')->assertForbidden();
});

it('cria o cupom de volta na hora, de uso único e com validade', function () {
    ['tenant' => $tenant] = salon();
    $gerente = createTenantUser($tenant, ['gerente']);

    $this->actingAs($gerente);
    $page = Livewire::actingAs($gerente)->test(WinbackPage::class)->set('quickCoupon', '15')->call('createCoupon');
    $coupon = Coupon::sole();
    expect($coupon->only(['code', 'percent', 'per_customer_limit', 'is_public', 'first_order_only']))
        ->toBe(['code' => 'VOLTE15', 'percent' => 15, 'per_customer_limit' => 1, 'is_public' => false, 'first_order_only' => false])
        ->and((int) round(now()->diffInDays($coupon->ends_at)))->toBeGreaterThanOrEqual(15)
        ->and($page->get('couponId'))->toBe($coupon->id);

    $page->call('createCoupon')->set('quickCoupon', 'frete')->call('createCoupon');
    expect(Coupon::orderBy('id')->pluck('code')->all())->toBe(['VOLTE15', 'VOLTE15-2', 'VOLTEFRETE'])
        ->and(Coupon::where('code', 'VOLTEFRETE')->value('type'))->toBe(CouponType::FreeDelivery);
});
