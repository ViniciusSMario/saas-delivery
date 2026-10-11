<?php

use App\Livewire\Tenant\Orders\Counter;
use App\Livewire\Tenant\Promotions\Coupons;
use App\Livewire\Tenant\Promotions\Loyalty as LoyaltyPage;
use App\Livewire\Tenant\Tables\Map;
use App\Livewire\Tenant\Tables\QrCodes;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Customers\Models\Customer;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Promotions\CouponRules;
use App\Modules\Promotions\Loyalty;
use App\Modules\Promotions\Models\Coupon;
use App\Modules\Promotions\Models\LoyaltyReward;
use App\Modules\Push\Models\PushSubscription;
use App\Modules\Push\Transports\FakePushTransport;
use App\Modules\Tables\Actions\ManageCommand;
use App\Modules\Tables\CommandStatus;
use App\Modules\Tables\Models\Command;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tables\TableOrdering;
use App\Support\Money;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(fn () => FakePushTransport::reset());

/** Pedido do cardápio (retirada) já concluído. */
function loyalOrder(Product $product, string $phone = '11977775555', int $quantity = 1, ?string $coupon = null): Order
{
    $order = app(PlaceOrder::class)->handle([
        'lines' => [line($product, $quantity)],
        'fulfillment' => 'pickup',
        'payment_method' => 'cash',
        'customer' => ['name' => 'Rita Moura', 'phone' => $phone],
        'coupon' => $coupon,
    ], OrderSource::Online);

    foreach ([OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Completed] as $status) {
        app(ChangeOrderStatus::class)->handle($order, $status);
    }

    return $order->fresh();
}

function enableLoyalty(array $config = []): void
{
    app(Loyalty::class)->save($config + ['enabled' => true, 'goal' => 3, 'reward_type' => 'fixed', 'reward_cents' => 2000, 'valid_days' => 30]);
}

function errorOf(Closure $action): ?string
{
    try {
        $action();
    } catch (ValidationException $e) {
        return collect($e->errors())->flatten()->first();
    }

    return null;
}

/** Mesa com o pedido pelo celular no modo escolhido. */
function qrTable(string $mode = 'open'): array
{
    $salon = salon();
    Setting::write(TableOrdering::SETTING, $mode);

    return $salon + ['url' => fn (DiningTable $table, string $path = '') => tenantUrl($salon['tenant'], '/cardapio/mesa/'.$table->fresh()->qr_token.$path)];
}

function cartJson(Product $product, int $quantity = 1): string
{
    return json_encode([['type' => 'product', 'id' => $product->id, 'quantity' => $quantity]]);
}

// ---------- Cartão fidelidade ----------

it('a cada N pedidos concluídos o cliente ganha um cupom só dele, contando a partir de quando o programa foi ligado', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();

    $this->travel(-5)->minutes();
    loyalOrder($burger); // antes de ligar: não conta
    $this->travelBack();
    enableLoyalty(['min_cents' => 2500]);
    $loyalty = app(Loyalty::class);
    $rita = Customer::where('phone', '11977775555')->sole();

    loyalOrder(Product::where('name', 'Refrigerante')->sole()); // R$ 8: abaixo do mínimo, não conta
    loyalOrder($burger);
    loyalOrder($burger);
    expect($loyalty->status($rita))->toMatchArray(['stamps' => 2, 'goal' => 3, 'missing' => 1])
        ->and(LoyaltyReward::count())->toBe(0);

    // Pedido em andamento não conta; ao concluir, completa o cartão.
    $third = loyalOrder($burger);
    $reward = LoyaltyReward::with('coupon')->sole();
    $coupon = $reward->coupon;
    expect($reward->order_id)->toBe($third->id)
        ->and($coupon->code)->toMatch('/^FIEL-[A-Z2-9]{5}$/')
        ->and($coupon->only(['customer_id', 'usage_limit', 'per_customer_limit', 'is_public']))->toBe(['customer_id' => $rita->id, 'usage_limit' => 1, 'per_customer_limit' => 1, 'is_public' => false])
        ->and($coupon->benefit())->toBe('R$ 20,00 de desconto')
        ->and((int) round(now()->diffInDays($coupon->ends_at)))->toBeGreaterThanOrEqual(30)
        ->and($loyalty->status($rita))->toMatchArray(['stamps' => 0, 'missing' => 3])
        ->and($loyalty->status($rita)['rewards']->pluck('code')->all())->toBe([$coupon->code]);

    // Cupom pessoal: outro telefone não usa; não aparece nas listas de cupons.
    expect(app(CouponRules::class)->problem($coupon, Money::parse('50'), '11966665555'))->toBe('Este cupom é de outro cliente.')
        ->and(app(CouponRules::class)->publicCoupons()->pluck('id'))->not->toContain($coupon->id);
    $dono = createTenantUser($tenant, ['dono']);
    $this->actingAs($dono);
    Livewire::actingAs($dono)->test(Coupons::class)->assertDontSee($coupon->code);

    // A dona do cartão usa: desconto de R$ 20 e o prêmio deixa de aparecer.
    $used = loyalOrder($burger, quantity: 2, coupon: $coupon->code);
    expect($used->discount_cents->format())->toBe('R$ 20,00')
        ->and($loyalty->status($rita)['rewards'])->toBeEmpty()
        ->and($loyalty->status($rita)['stamps'])->toBe(1) // o pedido com o prêmio também conta
        ->and($loyalty->stats())->toMatchArray(['members' => 1, 'rewards' => 1, 'used' => 1, 'used_sales_cents' => $used->total_cents->cents]);
});

it('mostra o cartão na página do pedido, avisa o prêmio no celular e oferece o prêmio no PDV', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    enableLoyalty(['goal' => 2]);

    $first = loyalOrder($burger);
    asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, "/cardapio/pedido/{$first->public_token}")))
        ->assertSee('Cartão fidelidade')->assertSee('A cada 2 pedidos, ganhe R$ 20,00 de desconto')->assertSee('Falta só 1 pedido para o seu prêmio!');
    asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, '/cardapio')))->assertSee('A cada 2 pedidos, ganhe R$ 20,00 de desconto');

    // Segundo pedido com os avisos ligados: o "entregue" já conta do prêmio.
    $second = app(PlaceOrder::class)->handle(['lines' => [line($burger)], 'fulfillment' => 'pickup', 'payment_method' => 'cash',
        'customer' => ['name' => 'Rita Moura', 'phone' => '11977775555']], OrderSource::Online);
    asCustomer($tenant, fn () => $this->postJson(tenantUrl($tenant, "/cardapio/pedido/{$second->public_token}/avisos"), [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.Str::random(24),
        'keys' => ['p256dh' => 'BKey'.Str::random(20), 'auth' => Str::random(16)],
    ]))->assertNoContent();
    foreach ([OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Completed] as $status) {
        app(ChangeOrderStatus::class)->handle($second->fresh(), $status);
    }
    $code = Coupon::whereNotNull('customer_id')->value('code');
    $last = collect(FakePushTransport::$sent)->where('order_id', $second->id)->last()['payload'];
    expect($last['title'])->toBe('Você ganhou R$ 20,00 de desconto!')->and($last['body'])->toContain($code);

    asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, "/cardapio/pedido/{$second->public_token}")))
        ->assertSee('Você ganhou R$ 20,00 de desconto!')->assertSee($code)->assertSee('?cupom='.$code, false);

    // No balcão, o telefone mostra o cartão e o prêmio, que entra com um toque.
    $caixa = createTenantUser($tenant, ['caixa']);
    $this->actingAs($caixa);
    Livewire::actingAs($caixa)->test(Counter::class)
        ->set('customerPhone', '(11) 97777-5555')
        ->assertSee('Fidelidade: 0 de 2 pedidos')->assertSee($code)
        ->call('useReward', $code)->assertSet('coupon', $code);
});

it('a tela de fidelidade liga o programa, valida a regra e mostra a prévia', function () {
    ['tenant' => $tenant] = salon();
    $gerente = createTenantUser($tenant, ['gerente']);
    $caixa = createTenantUser($tenant, ['caixa']);

    $this->actingAs($gerente);
    Livewire::actingAs($gerente)->test(LoyaltyPage::class)
        ->set('goal', '1')->call('save')->assertHasErrors('goal')
        ->set('goal', '8')->set('rewardType', 'percent')->set('rewardPercent', '15')->set('minOrder', '30')
        ->assertSee('A cada 8 pedidos, ganhe 15% de desconto · vale pedido a partir de R$ 30,00')
        ->set('enabled', true)->call('save')->assertHasNoErrors()->assertSee('Salvo');

    $config = app(Loyalty::class)->config();
    expect($config)->toMatchArray(['enabled' => true, 'goal' => 8, 'min_cents' => 3000, 'reward_type' => 'percent', 'reward_percent' => 15])
        ->and($config['started_at'])->not->toBeNull()
        ->and(AuditLog::where('action', 'loyalty.updated')->count())->toBe(1);

    $this->actingAs($caixa);
    Livewire::actingAs($caixa)->test(LoyaltyPage::class)->call('save')->assertForbidden();
});

// ---------- Pedido pela mesa (QR code) ----------

it('com a mesa aberta pelo garçom, o cliente pede pelo celular e acompanha a conta', function () {
    ['tenant' => $tenant, 'burger' => $burger, 'soda' => $soda, 't1' => $t1, 'url' => $url] = qrTable('open');
    $garcom = createTenantUser($tenant, ['garcom']);
    PushSubscription::create(['user_id' => $garcom->id, 'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.Str::random(20), 'public_key' => 'BKey'.Str::random(20), 'auth_token' => Str::random(16)]);

    // Mesa fechada: vê o cardápio, mas não pede.
    asCustomer($tenant, fn () => $this->get($url($t1)))->assertOk()->assertSee('Mesa 1')->assertSee('peça ao garçom para abrir a mesa');
    asCustomer($tenant, fn () => $this->post($url($t1, '/pedido'), ['cart' => cartJson($burger)]))->assertSessionHasErrors(['lines' => 'Para pedir pelo celular, peça ao garçom para abrir a mesa.']);
    expect(Order::count())->toBe(0);

    $command = app(ManageCommand::class)->open($t1, null, 2, $garcom);
    asCustomer($tenant, fn () => $this->get($url($t1)))->assertSee('Peça por aqui');
    asCustomer($tenant, fn () => $this->get($url($t1, '/pedido')))->assertOk()->assertSee('Pedido da mesa 1')->assertSee('Enviar para a cozinha');

    $response = asCustomer($tenant, fn () => $this->post($url($t1, '/pedido'), ['cart' => cartJson($burger, 2), 'name' => 'Rita', 'notes' => 'sem cebola']));
    $order = Order::latest('id')->first();
    $response->assertRedirect($url($t1).'?enviado='.$order->number.'#pedidos');
    expect($order->only(['command_id', 'customer_name', 'notes', 'via_qr']))->toBe(['command_id' => $command->id, 'customer_name' => 'Mesa 1 · Rita', 'notes' => 'sem cebola', 'via_qr' => true])
        ->and($order->source)->toBe(OrderSource::Table)->and($order->created_by)->toBeNull();

    // O garçom recebe o aviso; a conta mostra o pedido com taxa de serviço.
    $push = collect(FakePushTransport::$sent)->firstWhere('user_id', $garcom->id)['payload'];
    expect($push['title'])->toBe('Mesa 1 pediu pelo celular')->and($push['body'])->toBe('2× Hambúrguer');
    asCustomer($tenant, fn () => $this->get($url($t1).'?enviado='.$order->number))
        ->assertSee("Pedido #{$order->number} enviado para a cozinha!")->assertSee('2×')->assertSee('Hambúrguer')
        ->assertSee('R$ 60,00')->assertSee('Serviço (10%)')->assertSee('R$ 66,00');

    // Pedir a conta pelo celular: chamado no mapa, comanda em "conta pedida" e novos pedidos bloqueados.
    asCustomer($tenant, fn () => $this->post($url($t1, '/chamar'), ['reason' => 'bill']))->assertRedirect()->assertSessionHas('table_call', 'bill');
    expect($command->fresh()->status)->toBe(CommandStatus::Closing)
        ->and(collect(FakePushTransport::$sent)->pluck('payload.title'))->toContain('Mesa 1 · Pediu a conta');
    asCustomer($tenant, fn () => $this->post($url($t1, '/pedido'), ['cart' => cartJson($soda)]))
        ->assertSessionHasErrors(['lines' => 'A conta desta mesa já foi pedida. Para pedir mais, chame o garçom.']);

    $this->actingAs($garcom);
    Livewire::actingAs($garcom)->test(Map::class)->assertSee('Mesa 1 · Pediu a conta')
        ->call('answer', $t1->id)->assertDontSee('Mesa 1 · Pediu a conta');
});

it('no modo em que o cliente abre a mesa, o primeiro pedido abre a comanda (só no horário)', function () {
    ['tenant' => $tenant, 'burger' => $burger, 't2' => $t2, 'url' => $url] = qrTable('any');

    asCustomer($tenant, fn () => $this->post($url($t2, '/pedido'), ['cart' => cartJson($burger)]))->assertRedirect();
    $command = Command::sole();
    expect($command->table_id)->toBe($t2->id)->and($command->opened_by)->toBeNull()
        ->and(Order::sole()->command_id)->toBe($command->id);

    // Segundo pedido entra na mesma comanda.
    asCustomer($tenant, fn () => $this->post($url($t2, '/pedido'), ['cart' => cartJson($burger)]))->assertRedirect();
    expect(Command::count())->toBe(1)->and(Order::where('command_id', $command->id)->count())->toBe(2);

    // Fora do horário, sem comanda aberta: não abre.
    $t1 = DiningTable::where('name', '1')->sole();
    OpeningHour::query()->update(['is_closed' => true]);
    expect(errorOf(fn () => app(TableOrdering::class)->place($t1->fresh('openCommand'), [line($burger)], null, null)))->toBe('Estamos fechados agora.');
});

it('protege o pedido pela mesa: modo desligado, QR trocado, item fora do cardápio e limite de pedidos', function () {
    ['tenant' => $tenant, 'burger' => $burger, 't1' => $t1, 'url' => $url] = qrTable('off');

    asCustomer($tenant, fn () => $this->get($url($t1)))->assertOk()->assertSee('Os pedidos pelo celular estão desligados');
    asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, '/cardapio/mesa/naoexiste1234567890')))->assertNotFound();

    // QR novo: o antigo para de funcionar.
    $old = $url($t1);
    $t1->renewQrToken();
    asCustomer($tenant, fn () => $this->get($old))->assertNotFound();
    asCustomer($tenant, fn () => $this->get($url($t1)))->assertOk();

    // Mesa aberta pelo garçom, modo ligado: item de categoria desativada é recusado (como no pedido online).
    Setting::write(TableOrdering::SETTING, 'open');
    $garcom = createTenantUser($tenant, ['garcom']);
    app(ManageCommand::class)->open($t1, null, 2, $garcom);
    $burger->category->update(['is_active' => false]);
    $ordering = app(TableOrdering::class);
    expect(errorOf(fn () => $ordering->place($t1->fresh('openCommand'), [line($burger)], null, null)))->toBe('Um item do pedido não está mais no cardápio.');
    $burger->category->update(['is_active' => true]);

    // Limite por mesa (foto do QR mandando pedido de fora).
    RateLimiter::clear('table-qr:'.$tenant->getKey().'|'.$t1->id);
    foreach (range(1, TableOrdering::MAX_ORDERS) as $i) {
        $ordering->place($t1->fresh('openCommand'), [line($burger)], null, null);
    }
    expect(errorOf(fn () => $ordering->place($t1->fresh('openCommand'), [line($burger)], null, null)))->toBe('Muitos pedidos seguidos nesta mesa. Chame o garçom.');
});

it('a página de QR codes imprime um código por mesa, troca o modo e gera QR novo', function () {
    ['tenant' => $tenant, 't1' => $t1] = qrTable('open');
    $dono = createTenantUser($tenant, ['dono']);
    $caixa = createTenantUser($tenant, ['caixa']);
    $token = $t1->fresh()->qr_token;

    $this->actingAs($dono);
    Livewire::actingAs($dono)->test(QrCodes::class)
        ->assertSee('Mesa 1')->assertSee('Mesa 2')->assertSee('<svg', false)->assertSee('/cardapio/mesa/'.$token)
        ->set('mode', 'any')->assertSee('Salvo')
        ->call('renew', $t1->id)->assertDontSee('/cardapio/mesa/'.$token);
    expect(TableOrdering::mode())->toBe('any')
        ->and($t1->fresh()->qr_token)->not->toBe($token)
        ->and(DiningTable::create(['name' => '9'])->qr_token)->toHaveLength(24);

    // Página só para quem cadastra mesas.
    $this->actingAs($caixa);
    $this->get(tenantUrl($tenant, '/mesas/qrcodes'))->assertForbidden();
});
