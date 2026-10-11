<?php

use App\Livewire\Tenant\Establishment\EditSettings;
use App\Modules\Delivery\Actions\Deliveries;
use App\Modules\Establishment\Models\Establishment;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Push\Models\OrderPushSubscription;
use App\Modules\Push\Transports\FakePushTransport;
use App\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(fn () => FakePushTransport::reset());

/** Inscrição que o navegador do cliente manda ao tocar em "Avisar". */
function browserSubscription(?string $endpoint = null): array
{
    return [
        'endpoint' => $endpoint ?? 'https://fcm.googleapis.com/fcm/send/'.Str::random(24),
        'keys' => ['p256dh' => 'BKey'.Str::random(20), 'auth' => Str::random(16)],
        'contentEncoding' => 'aes128gcm',
    ];
}

/** Requisição do cliente pelo cardápio; depois o teste volta ao contexto do restaurante. */
function asCustomer(Tenant $tenant, Closure $request): mixed
{
    try {
        return $request();
    } finally {
        activateTenant($tenant);
    }
}

/** @return list<array> avisos enviados ao cliente daquele pedido */
function customerPushes(Order $order): array
{
    return array_values(array_filter(FakePushTransport::$sent, fn ($sent) => $sent['order_id'] === $order->id));
}

it('a página do pedido mostra previsão, horário de cada etapa, entregador e contato', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    Establishment::current()->fill(['phone' => '(11) 3333-4444'])->save();
    $ana = createTenantUser($tenant, ['entregador'], ['name' => 'Ana Souza']);

    $this->travelTo(CarbonImmutable::parse('2026-10-07 19:00', 'America/Sao_Paulo'));
    $order = readyDeliveryOrder($burger, ready: false);
    $page = fn () => asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, "/cardapio/pedido/{$order->public_token}"))->assertOk());

    $page()->assertSee('Previsão de entrega')->assertSee('19:45') // 45 min, o padrão das Configurações
        ->assertSee('Aceito e na cozinha')
        ->assertSee('Quer ser avisado?')
        ->assertSee('https://wa.me/551133334444', false)
        ->assertSee('name="push-key"', false);

    $this->travel(15)->minutes();
    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Ready);
    app(Deliveries::class)->take($order->fresh(), $ana);
    $page()->assertSee('Com Ana')->assertDontSee('Souza')->assertSee('19:15'); // hora em que ficou pronto

    // Entregue: sem previsão e sem convite para avisos.
    app(Deliveries::class)->complete($order->fresh(), $ana);
    $page()->assertDontSee('Previsão de entrega')->assertDontSee('Quer ser avisado?')->assertSee('Bom apetite!');

    // O restaurante pode esconder a previsão.
    Livewire::actingAs(createTenantUser($tenant, ['gerente']))->test(EditSettings::class)
        ->set('eta_delivery_minutes', 0)->call('save')->assertHasNoErrors();
    $outro = readyDeliveryOrder($burger, ready: false);
    asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, "/cardapio/pedido/{$outro->public_token}")))
        ->assertOk()->assertDontSee('Previsão de entrega');
});

it('o cliente liga os avisos do pedido sem conta e só com serviços de push conhecidos', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    $order = readyDeliveryOrder($burger, ready: false);
    $url = tenantUrl($tenant, "/cardapio/pedido/{$order->public_token}/avisos");
    $subscribe = fn (array $body, string $to = '') => asCustomer($tenant, fn () => $this->postJson($to ?: $url, $body));
    $phone = browserSubscription();

    $subscribe($phone)->assertNoContent();
    $subscribe($phone)->assertNoContent(); // tocar de novo não duplica
    $saved = OrderPushSubscription::sole();
    expect($saved->order_id)->toBe($order->id)
        ->and($saved->page_url)->toBe("/cardapio/pedido/{$order->public_token}")
        ->and($saved->icon_url)->toBeNull(); // sem logo: o aviso usa o ícone padrão

    // Pelo endereço com o slug (meusistema.com/cardapio/{slug}), o aviso abre o mesmo endereço.
    $subscribe(browserSubscription(), "http://localhost/cardapio/{$tenant->slug}/pedido/{$order->public_token}/avisos")->assertNoContent();
    expect(OrderPushSubscription::latest('id')->first()->page_url)->toBe("/cardapio/{$tenant->slug}/pedido/{$order->public_token}");

    $subscribe(browserSubscription('https://10.0.0.5/push'))->assertStatus(422);
    $subscribe(browserSubscription('https://fcm.googleapis.com.evil.com/x'))->assertStatus(422);
    $subscribe(browserSubscription(), tenantUrl($tenant, '/cardapio/pedido/'.Str::random(32).'/avisos'))->assertNotFound();

    // No máximo 5 aparelhos por pedido.
    foreach (range(1, 3) as $i) {
        $subscribe(browserSubscription())->assertNoContent();
    }
    $subscribe(browserSubscription())->assertStatus(422);

    // Desligar apaga só a inscrição daquele aparelho.
    asCustomer($tenant, fn () => $this->deleteJson($url, ['endpoint' => $phone['endpoint']]))->assertNoContent();
    expect(OrderPushSubscription::where('endpoint', $phone['endpoint'])->exists())->toBeFalse()
        ->and(OrderPushSubscription::count())->toBe(4);

    // Pedido terminado não aceita mais inscrição.
    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Cancelled, reason: 'teste');
    $subscribe(browserSubscription())->assertStatus(422);
});

it('cada etapa avisa o celular do cliente e o aviso some quando o pedido termina', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    $ana = createTenantUser($tenant, ['entregador'], ['name' => 'Ana Souza']);
    $this->travelTo(CarbonImmutable::parse('2026-10-07 19:00', 'America/Sao_Paulo'));
    $subscribe = fn (Order $order) => asCustomer($tenant, fn () => $this->postJson(tenantUrl($tenant, "/cardapio/pedido/{$order->public_token}/avisos"), browserSubscription()));

    $order = readyDeliveryOrder($burger, ready: false); // já está Em preparo
    $outro = readyDeliveryOrder($burger, ready: false);
    $subscribe($order)->assertNoContent();
    FakePushTransport::reset();

    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Ready);
    app(ChangeOrderStatus::class)->handle($outro, OrderStatus::Ready); // sem inscrição: nada
    app(Deliveries::class)->take($order->fresh(), $ana);
    app(Deliveries::class)->complete($order->fresh(), $ana);

    $sent = customerPushes($order);
    expect(array_column(array_column($sent, 'payload'), 'title'))->toBe([
        "Pedido #{$order->number} pronto",
        "Pedido #{$order->number} saiu para entrega",
        "Pedido #{$order->number} entregue",
    ]);
    expect($sent[1]['payload'])->toMatchArray([
        'body' => 'Com Ana · previsão 19:45',
        'url' => "/cardapio/pedido/{$order->public_token}",
        'tag' => "acompanhar-{$order->id}",
    ]);
    expect(customerPushes($outro))->toBeEmpty()
        ->and(OrderPushSubscription::count())->toBe(0); // pedido entregue: inscrição apagada

    // Retirada: "pronto para retirada"; cancelamento traz o motivo.
    $pickup = app(PlaceOrder::class)->handle([
        'lines' => [line($burger)], 'fulfillment' => 'pickup', 'payment_method' => 'cash',
        'customer' => ['name' => 'Rita', 'phone' => '11977775555'],
    ], OrderSource::Online);
    $subscribe($pickup)->assertNoContent();
    app(ChangeOrderStatus::class)->handle($pickup, OrderStatus::Preparing);
    app(ChangeOrderStatus::class)->handle($pickup, OrderStatus::Ready);
    app(ChangeOrderStatus::class)->handle($pickup, OrderStatus::Cancelled, reason: 'cliente não veio');
    expect(array_column(array_column(customerPushes($pickup), 'payload'), 'title'))->toBe([
        "Pedido #{$pickup->number} aceito",
        "Pedido #{$pickup->number} pronto para retirada",
        "Pedido #{$pickup->number} cancelado",
    ])->and(customerPushes($pickup)[2]['payload']['body'])->toBe('Motivo: cliente não veio');
});

it('celular do cliente que desinstalou sai da lista sem atrapalhar o pedido', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    $order = readyDeliveryOrder($burger, ready: false);
    $velho = browserSubscription();
    $novo = browserSubscription();
    foreach ([$velho, $novo] as $phone) {
        asCustomer($tenant, fn () => $this->postJson(tenantUrl($tenant, "/cardapio/pedido/{$order->public_token}/avisos"), $phone))->assertNoContent();
    }
    FakePushTransport::$expired = [$velho['endpoint']];

    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Ready);
    expect(OrderPushSubscription::pluck('endpoint')->all())->toBe([$novo['endpoint']])
        ->and($order->fresh()->status)->toBe(OrderStatus::Ready);
});
