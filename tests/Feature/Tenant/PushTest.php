<?php

use App\Modules\Access\Models\User;
use App\Modules\Delivery\Actions\Deliveries;
use App\Modules\Establishment\Models\Establishment;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Push\Models\PushSubscription;
use App\Modules\Push\PushTransport;
use App\Modules\Push\StaffAlerts;
use App\Modules\Push\Transports\FakePushTransport;
use App\Modules\Tables\Actions\ManageCommand;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

beforeEach(fn () => FakePushTransport::reset());

/** Celular inscrito (endpoint do serviço de push do Chrome). */
function phoneOf(User $user, ?string $endpoint = null): PushSubscription
{
    return PushSubscription::create([
        'user_id' => $user->id,
        'endpoint' => $endpoint ?? 'https://fcm.googleapis.com/fcm/send/'.Str::random(20),
        'public_key' => 'BPublicKey'.Str::random(20),
        'auth_token' => Str::random(16),
    ]);
}

/** @return list<array{user_id: int, payload: array}> */
function pushesTo(User $user): array
{
    return array_values(array_filter(FakePushTransport::$sent, fn ($sent) => $sent['user_id'] === $user->id));
}

it('cada restaurante tem o próprio app instalável, com nome e ícones', function () {
    $tenant = tenantBySlug('teste1');

    $manifest = $this->get(tenantUrl($tenant, '/manifest.webmanifest'))->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json');
    expect($manifest->json('name'))->toBe(inTenant($tenant, fn () => Establishment::current()->trade_name ?: $tenant->name))
        ->and($manifest->json('start_url'))->toBe('/')
        ->and($manifest->json('display'))->toBe('standalone')
        ->and(collect($manifest->json('icons'))->pluck('purpose')->all())->toBe(['any', 'any', 'maskable']);

    foreach (['192' => 192, '512' => 512, 'maskable' => 512, 'apple' => 180, 'badge' => 96] as $variant => $size) {
        $png = $this->get(tenantUrl($tenant, "/app-icon/{$variant}.png"))->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();
        expect(getimagesizefromstring($png)[0])->toBe($size);
    }

    $this->get(tenantUrl($tenant, '/app-icon/999.png'))->assertNotFound();

    // A tela de login já oferece o app (dá para instalar antes de entrar).
    $this->get(tenantUrl($tenant, '/login'))->assertOk()->assertSee('rel="manifest"', false)->assertSee('name="push-key"', false);
});

it('o celular liga e desliga as notificações e só aceita serviços de push conhecidos', function () {
    $tenant = provisionTenant('push');
    $garcom = createTenantUser($tenant, ['garcom']);
    $outro = createTenantUser($tenant, ['garcom']);
    $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123';
    $body = ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BKey123', 'auth' => 'authToken'], 'contentEncoding' => 'aes128gcm'];

    $this->actingAs($garcom)->postJson(tenantUrl($tenant, '/notificacoes/assinatura'), $body)->assertNoContent();
    expect(inTenant($tenant, fn () => PushSubscription::where('endpoint', $endpoint)->value('user_id')))->toBe($garcom->id);

    // Endereço interno ou sem HTTPS: recusado (o servidor nunca chama endereços escolhidos pelo usuário).
    $this->postJson(tenantUrl($tenant, '/notificacoes/assinatura'), ['endpoint' => 'https://10.0.0.5/push'] + $body)->assertStatus(422);
    $this->postJson(tenantUrl($tenant, '/notificacoes/assinatura'), ['endpoint' => 'https://fcm.googleapis.com.evil.com/x'] + $body)->assertStatus(422);
    $this->postJson(tenantUrl($tenant, '/notificacoes/assinatura'), ['endpoint' => 'http://fcm.googleapis.com/x'] + $body)->assertStatus(422);
    $this->postJson(tenantUrl($tenant, '/notificacoes/assinatura'), ['endpoint' => 'https://web.push.apple.com/QGuQ'] + $body)->assertNoContent();

    // Outra pessoa entra no mesmo celular: a inscrição passa a ser dela.
    $this->flushSession();
    $this->actingAs($outro)->postJson(tenantUrl($tenant, '/notificacoes/assinatura'), $body)->assertNoContent();
    expect(inTenant($tenant, fn () => PushSubscription::where('endpoint', $endpoint)->value('user_id')))->toBe($outro->id);

    // Ao sair da conta, este celular para de receber os avisos dela.
    $this->post(tenantUrl($tenant, '/logout'));
    expect(inTenant($tenant, fn () => PushSubscription::where('endpoint', $endpoint)->exists()))->toBeFalse();

    // Desativar só apaga a própria inscrição.
    $this->flushSession();
    $this->actingAs($outro)->deleteJson(tenantUrl($tenant, '/notificacoes/assinatura'), ['endpoint' => 'https://web.push.apple.com/QGuQ'])->assertNoContent();
    expect(inTenant($tenant, fn () => PushSubscription::where('endpoint', 'https://web.push.apple.com/QGuQ')->exists()))->toBeTrue();
    $this->flushSession();
    $this->actingAs($garcom)->deleteJson(tenantUrl($tenant, '/notificacoes/assinatura'), ['endpoint' => 'https://web.push.apple.com/QGuQ'])->assertNoContent();
    expect(inTenant($tenant, fn () => PushSubscription::count()))->toBe(0);
});

it('entrega pronta avisa os entregadores; prato de mesa pronto avisa os garçons', function () {
    ['tenant' => $tenant, 'burger' => $burger, 't1' => $t1] = salon();
    $entregador = createTenantUser($tenant, ['entregador']);
    $garcom = createTenantUser($tenant, ['garcom']);
    $gerente = createTenantUser($tenant, ['gerente']);
    $semCelular = createTenantUser($tenant, ['entregador']);
    $inativo = createTenantUser($tenant, ['entregador'], ['is_active' => false]);
    foreach ([$entregador, $garcom, $gerente, $inativo] as $user) {
        phoneOf($user);
    }

    $delivery = readyDeliveryOrder($burger);
    expect(pushesTo($entregador))->toHaveCount(1)
        ->and(pushesTo($garcom))->toBeEmpty()
        ->and(pushesTo($gerente))->toBeEmpty()
        ->and(pushesTo($semCelular))->toBeEmpty()
        ->and(pushesTo($inativo))->toBeEmpty();

    $payload = pushesTo($entregador)[0]['payload'];
    expect($payload['title'])->toBe("Entrega pronta · #{$delivery->number}")
        ->and($payload['body'])->toStartWith('Centro · Rita · cobrar R$')
        ->and($payload['url'])->toBe('/entregas')
        ->and($payload['tag'])->toBe("entrega-{$delivery->id}");

    FakePushTransport::reset();
    $manage = app(ManageCommand::class);
    $order = $manage->addItems($manage->open($t1, null, 2, $garcom), [line($burger, 2)], $garcom);
    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Preparing);
    expect(FakePushTransport::$sent)->toBeEmpty(); // só avisa quando fica pronto

    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Ready);
    expect(pushesTo($garcom))->toHaveCount(1)->and(pushesTo($entregador))->toBeEmpty();
    expect(pushesTo($garcom)[0]['payload'])->toMatchArray(['title' => 'Mesa 1 · pedido pronto', 'body' => '2× Hambúrguer', 'url' => '/mesas/prontos']);
});

it('a gerência mandando a entrega avisa só aquele entregador', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    $ana = createTenantUser($tenant, ['entregador']);
    $bia = createTenantUser($tenant, ['entregador']);
    $gerente = createTenantUser($tenant, ['gerente']);
    phoneOf($ana);
    phoneOf($bia);

    $order = readyDeliveryOrder($burger);
    FakePushTransport::reset();

    app(Deliveries::class)->assign($order, $ana, $gerente);
    expect(pushesTo($ana))->toHaveCount(1)->and(pushesTo($bia))->toBeEmpty()
        ->and(pushesTo($ana)[0]['payload']['title'])->toBe("Entrega para você · #{$order->number}");

    // Troca para a Bia com o pedido já na rua: ela é avisada.
    app(Deliveries::class)->assign($order->fresh(), $bia, $gerente);
    expect(pushesTo($bia))->toHaveCount(1);

    // O entregador pegando sozinho não gera aviso para ele mesmo.
    FakePushTransport::reset();
    $outra = readyDeliveryOrder($burger);
    FakePushTransport::reset();
    app(Deliveries::class)->take($outra, $ana);
    expect(FakePushTransport::$sent)->toBeEmpty();
});

it('celular que desinstalou o app sai da lista; aviso que falha não atrapalha o pedido', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    $entregador = createTenantUser($tenant, ['entregador']);
    $velho = phoneOf($entregador, 'https://fcm.googleapis.com/fcm/send/velho');
    $novo = phoneOf($entregador);
    FakePushTransport::$expired = [$velho->endpoint];

    readyDeliveryOrder($burger);
    expect(PushSubscription::whereKey($velho->id)->exists())->toBeFalse()
        ->and($novo->fresh()->last_used_at)->not->toBeNull();

    // Serviço de push fora do ar: o pedido fica pronto do mesmo jeito.
    app()->instance(PushTransport::class, new class implements PushTransport
    {
        public function send(Collection $subscriptions, array $payload): array
        {
            throw new RuntimeException('serviço de push fora do ar');
        }
    });
    $order = readyDeliveryOrder($burger);
    expect($order->status)->toBe(OrderStatus::Ready);
});

it('garçom e entregador ativam e testam pela tela; a gerência só vê a instalação', function () {
    $tenant = provisionTenant('push');
    $entregador = createTenantUser($tenant, ['entregador']);
    $gerente = createTenantUser($tenant, ['gerente']);

    inTenant($tenant, function () use ($entregador, $gerente) {
        expect(StaffAlerts::receivesAlerts($entregador))->toBeTrue()->and(StaffAlerts::receivesAlerts($gerente))->toBeFalse();
    });

    $this->actingAs($entregador)->get(tenantUrl($tenant, '/minha-conta'))->assertOk()
        ->assertSee('App no celular')->assertSee('Ativar notificações')->assertSee('name="push-user"', false);
    $this->get(tenantUrl($tenant, '/entregas'))->assertOk()->assertSee('Ative as notificações para saber na hora');

    $this->postJson(tenantUrl($tenant, '/notificacoes/teste'))->assertStatus(422);
    inTenant($tenant, fn () => phoneOf($entregador));
    $this->postJson(tenantUrl($tenant, '/notificacoes/teste'))->assertStatus(202);
    expect(pushesTo($entregador)[0]['payload']['title'])->toBe('Notificações ativadas');

    $this->flushSession();
    $this->actingAs($gerente)->get(tenantUrl($tenant, '/minha-conta'))->assertOk()
        ->assertSee('App no celular')->assertDontSee('Ativar notificações');
});
