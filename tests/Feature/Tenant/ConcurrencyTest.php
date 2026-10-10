<?php

use App\Livewire\Tenant\Tables\ReadyToServe;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Delivery\Actions\Deliveries;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Tables\Actions\ManageCommand;
use App\Modules\Tables\ServeQueue;
use App\Tenancy\Models\Tenant;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** Pedido de entrega pago na entrega, já pronto na cozinha. */
function readyDeliveryOrder(Product $product, bool $ready = true): Order
{
    $order = app(PlaceOrder::class)->handle([
        'lines' => [line($product)],
        'fulfillment' => 'delivery',
        'payment_method' => 'cash',
        'customer' => ['name' => 'Rita', 'phone' => '11977775555'],
        'address' => ['street' => 'Rua A', 'number' => '10', 'district' => 'Centro', 'city' => 'Campinas'],
    ], OrderSource::Online);

    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Preparing);

    if ($ready) {
        app(ChangeOrderStatus::class)->handle($order, OrderStatus::Ready);
    }

    return $order->fresh();
}

function namedUser(Tenant $tenant, string $role, string $name)
{
    return createTenantUser($tenant, [$role], ['name' => $name]);
}

/** Espera a ValidationException com a mensagem indicada. */
function expectRefused(Closure $action, string $message): void
{
    try {
        $action();
    } catch (ValidationException $e) {
        expect(collect($e->errors())->flatten()->first())->toBe($message);

        return;
    }

    test()->fail("Esperava a recusa: {$message}");
}

it('dois entregadores no mesmo pedido: só o primeiro leva e o outro sabe quem levou', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    $ana = namedUser($tenant, 'entregador', 'Ana Entregadora');
    $bia = namedUser($tenant, 'entregador', 'Bia Entregadora');
    $deliveries = app(Deliveries::class);

    $order = readyDeliveryOrder($burger);
    // Telas carregadas antes da Ana tocar: ainda mostram o pedido como pronto.
    $seenByBia = $order->fresh();
    $seenByAna = $order->fresh();

    $deliveries->take($order, $ana);
    expectRefused(fn () => $deliveries->take($seenByBia, $bia), "Ana Entregadora já pegou o pedido #{$order->number}.");

    // Toque duplo da Ana (com a tela desatualizada): conta como um só.
    $deliveries->take($seenByAna, $ana);

    $order->refresh();
    expect($order->courier_id)->toBe($ana->id)
        ->and($order->status)->toBe(OrderStatus::Dispatched)
        ->and($order->history()->where('to_status', OrderStatus::Dispatched->value)->count())->toBe(1);
});

it('pegar a entrega é um passo só: nada fica gravado pela metade', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    $ana = namedUser($tenant, 'entregador', 'Ana Entregadora');

    // Ainda na cozinha: recusa e não grava entregador.
    $cooking = readyDeliveryOrder($burger, ready: false);
    expectRefused(fn () => app(Deliveries::class)->take($cooking, $ana), "O pedido #{$cooking->number} não está mais disponível para sair (Em preparo).");
    expect($cooking->fresh()->courier_id)->toBeNull()->and($cooking->fresh()->dispatched_at)->toBeNull();

    // Regra extra recusada dentro da trava: status e campos extras voltam juntos.
    $ready = readyDeliveryOrder($burger);
    expect(fn () => app(ChangeOrderStatus::class)->handle(
        $ready,
        OrderStatus::Dispatched,
        guard: fn () => throw ValidationException::withMessages(['status' => 'não']),
        attributes: ['courier_id' => $ana->id, 'dispatched_at' => now()],
    ))->toThrow(ValidationException::class);

    expect($ready->fresh()->status)->toBe(OrderStatus::Ready)->and($ready->fresh()->courier_id)->toBeNull();
});

it('entrega concluída duas vezes entra no caixa uma vez só; outro entregador não conclui', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    $ana = namedUser($tenant, 'entregador', 'Ana Entregadora');
    $bia = namedUser($tenant, 'entregador', 'Bia Entregadora');
    $gerente = namedUser($tenant, 'gerente', 'Gina Gerente');
    openCash(userIn($tenant, ['caixa']));
    $deliveries = app(Deliveries::class);

    $order = readyDeliveryOrder($burger);
    $deliveries->take($order, $ana);

    expectRefused(fn () => $deliveries->complete($order->fresh(), $bia), "O pedido #{$order->number} está com Ana Entregadora.");

    $stale = $order->fresh();
    $deliveries->complete($order->fresh(), $ana);
    $deliveries->complete($stale, $ana); // toque duplo

    expect($order->fresh()->status)->toBe(OrderStatus::Completed)
        ->and(CashMovement::where('order_id', $order->id)->count())->toBe(1);

    // Gerência tocando depois: aviso de quem entregou, sem lançar de novo no caixa.
    expectRefused(fn () => $deliveries->complete($stale, $gerente), "O pedido #{$order->number} já foi entregue (Ana Entregadora).");
    expect(CashMovement::where('order_id', $order->id)->count())->toBe(1);
});

it('gerência não troca o entregador de um pedido que já foi entregue', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    $ana = namedUser($tenant, 'entregador', 'Ana Entregadora');
    $bia = namedUser($tenant, 'entregador', 'Bia Entregadora');
    $gerente = namedUser($tenant, 'gerente', 'Gina Gerente');
    $deliveries = app(Deliveries::class);

    $order = readyDeliveryOrder($burger);
    $deliveries->take($order, $ana);
    $seenByManager = $order->fresh(); // na rua, na tela da gerência
    $deliveries->complete($order->fresh(), $ana);

    expectRefused(fn () => $deliveries->assign($seenByManager, $bia, $gerente), "O pedido #{$order->number} não está mais na rua (Concluído).");
    expect($order->fresh()->courier_id)->toBe($ana->id);
});

it('garçom reserva o prato com "Vou levar": o outro vê com quem está e não serve no lugar', function () {
    ['tenant' => $tenant, 'burger' => $burger, 't1' => $t1] = salon();
    $gabriel = namedUser($tenant, 'garcom', 'Gabriel Garçom');
    $bruno = namedUser($tenant, 'garcom', 'Bruno Garçom');
    $gerente = namedUser($tenant, 'gerente', 'Gina Gerente');
    $queue = app(ServeQueue::class);
    $manage = app(ManageCommand::class);

    $order = $manage->addItems($manage->open($t1, null, 2, $gabriel), [line($burger)], $gabriel);
    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Preparing);
    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Ready);
    $seenByBruno = $order->fresh();

    $queue->claim($order->fresh(), $gabriel);
    $queue->claim($order->fresh(), $gabriel); // toque duplo
    expectRefused(fn () => $queue->claim($seenByBruno, $bruno), "Gabriel Garçom já vai levar o pedido #{$order->number}.");

    expect(ServeQueue::countFor($gabriel))->toBe(1)->and(ServeQueue::countFor($bruno))->toBe(0);

    expectRefused(fn () => $queue->serve($seenByBruno, $bruno), "O pedido #{$order->number} está com Gabriel Garçom: só quem vai levar marca como servido.");
    expectRefused(fn () => $queue->release($seenByBruno, $bruno), "O pedido #{$order->number} está com Gabriel Garçom.");

    // A gerência solta a reserva; o Bruno leva e serve.
    $queue->release($order->fresh(), $gerente);
    expect($order->fresh()->runner_id)->toBeNull();
    $queue->claim($order->fresh(), $bruno);
    $queue->serve($order->fresh(), $bruno);
    $queue->serve($seenByBruno, $bruno); // toque duplo

    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
    expectRefused(fn () => $queue->serve($seenByBruno, $gabriel), "O pedido #{$order->number} já foi servido por Bruno Garçom.");
    expectRefused(fn () => $queue->claim($seenByBruno, $gabriel), "O pedido #{$order->number} já foi servido por Bruno Garçom.");
});

it('na tela, o segundo garçom vê o aviso em vez de erro de página', function () {
    ['tenant' => $tenant, 'burger' => $burger, 't1' => $t1] = salon();
    $gabriel = namedUser($tenant, 'garcom', 'Gabriel Garçom');
    $bruno = namedUser($tenant, 'garcom', 'Bruno Garçom');
    $manage = app(ManageCommand::class);

    $order = $manage->addItems($manage->open($t1, null, 2, $gabriel), [line($burger)], $gabriel);
    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Preparing);
    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Ready);

    // As duas telas abertas ao mesmo tempo (o usuário logado no teste é trocado antes de cada toque).
    $brunoScreen = Livewire::actingAs($bruno)->test(ReadyToServe::class)->assertSeeHtml('wire:click="claim(');
    $gabrielScreen = Livewire::actingAs($gabriel)->test(ReadyToServe::class);
    $gabrielScreen->call('claim', $order->id)->assertHasNoErrors()->assertSee('Você vai levar')->assertSee('Servido');

    $this->actingAs($bruno);
    $brunoScreen->call('claim', $order->id)->assertHasErrors('status')->assertSee('Gabriel Garçom já vai levar')->assertSee('Gabriel Garçom está levando')->assertDontSeeHtml('wire:click="claim(');

    $this->actingAs($gabriel);
    $gabrielScreen->call('serve', $order->id)->assertHasNoErrors()->assertSee("Pedido #{$order->number} servido.");

    $this->actingAs($bruno);
    $brunoScreen->call('serve', $order->id)->assertHasErrors('status')->assertSee('já foi servido por Gabriel Garçom');
});
