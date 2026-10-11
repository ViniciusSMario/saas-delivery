<?php

use App\Livewire\Tenant\Reports\Index as ReportsIndex;
use App\Modules\Delivery\Actions\Deliveries;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Reports\OperationsReport;
use App\Modules\Reports\SalesReport;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/** Leva o pedido pelas etapas, avançando o relógio: aceite, preparo e (entrega ou retirada). */
function runOrder(Order $order, int $accept, int $prep, ?Closure $finish = null): Order
{
    $status = app(ChangeOrderStatus::class);

    test()->travel($accept)->minutes();
    $status->handle($order, OrderStatus::Preparing);
    test()->travel($prep)->minutes();
    $status->handle($order, OrderStatus::Ready);

    if ($finish) {
        $finish($order->fresh());
    }

    return $order->fresh();
}

it('mede aceite, preparo, entrega e o desempenho de cada entregador', function () {
    ['tenant' => $tenant, 'burger' => $burger, 'gerente' => $gerente] = shop();
    Setting::write('delivery_fee_cents', 500);
    $ana = createTenantUser($tenant, ['entregador'], ['name' => 'Ana Entregadora']);
    $bia = createTenantUser($tenant, ['entregador'], ['name' => 'Bia Entregadora']);
    $deliveries = app(Deliveries::class);
    $deliverWith = fn ($courier, int $road) => function (Order $order) use ($deliveries, $courier, $road) {
        $deliveries->take($order, $courier);
        test()->travel($road)->minutes();
        $deliveries->complete($order->fresh(), $courier);
    };

    // Quarta-feira, 15h (horário do restaurante).
    $this->travelTo(CarbonImmutable::parse('2026-10-07 15:00', 'America/Sao_Paulo'));
    runOrder(onlineOrder($burger, 1, '11955550001', 'delivery'), accept: 3, prep: 12, finish: $deliverWith($ana, 20));
    runOrder(onlineOrder($burger, 1, '11955550002', 'delivery'), accept: 1, prep: 10, finish: $deliverWith($bia, 50));
    runOrder(onlineOrder($burger, 2, '11955550003'), accept: 5, prep: 8, finish: function (Order $order) {
        test()->travel(2)->minutes();
        app(ChangeOrderStatus::class)->handle($order, OrderStatus::Completed);
    });
    app(ChangeOrderStatus::class)->handle(onlineOrder($burger, 1), OrderStatus::Cancelled, reason: 'cliente desistiu');

    $report = app(OperationsReport::class);
    [$from, $to] = app(SalesReport::class)->range('2026-10-07', '2026-10-07');
    $minutes = fn (?float $seconds) => $seconds === null ? null : round($seconds / 60, 1);

    $all = $report->timings($from, $to)['all'];
    expect($all['orders'])->toBe(3) // o cancelado não entra
        ->and($minutes($all['accept']))->toBe(3.0)   // (3 + 1 + 5) / 3
        ->and($minutes($all['prep']))->toBe(10.0)    // (12 + 10 + 8) / 3
        ->and($minutes($all['road']))->toBe(35.0)    // (20 + 50) / 2, só entregas
        ->and($minutes($all['door']))->toBe(37.0);   // (35 + 61 + 15) / 3

    $byType = $report->timings($from, $to)['by_fulfillment']->keyBy('label');
    expect($byType['Entrega']['orders'])->toBe(2)->and($minutes($byType['Retirada']['door']))->toBe(15.0)
        ->and($byType['Retirada']['road'])->toBeNull();

    $couriers = $report->couriers($from, $to)->keyBy('name');
    expect($couriers['Ana Entregadora'])->toMatchArray(['deliveries' => 1, 'slow' => 0])
        ->and($couriers['Ana Entregadora']['fees']->format())->toBe('R$ 5,00')
        ->and($minutes($couriers['Ana Entregadora']['road']))->toBe(20.0)
        ->and($couriers['Bia Entregadora']['slow'])->toBe(1); // 50 min na rua

    expect(array_sum(array_column($report->byHour($from, $to), 'count')))->toBe(3)
        ->and($report->byHour($from, $to)[15]['count'])->toBe(2) // 15h00 e 15h35; o terceiro entrou 16h36
        ->and(OperationsReport::duration(185))->toBe('3 min')
        ->and(OperationsReport::duration(3900))->toBe('1h05')
        ->and(OperationsReport::duration(null))->toBe('—');

    // Pedido esquecido aberto de um dia para o outro não estraga a média.
    runOrder(onlineOrder($burger, 1, '11955550004'), accept: 2, prep: 300);
    expect($minutes($report->timings($from, $to)['all']['prep']))->toBe(10.0);

    // Mapa de calor e vendas por hora (no fuso do restaurante: quarta = 3).
    $sales = app(SalesReport::class);
    $grid = $sales->weekdayHours($from, $to);
    expect($grid[3][15])->toBe(2)->and(array_sum($grid[3]))->toBe(4)->and(array_sum(array_map('array_sum', $grid)))->toBe(4)
        ->and(array_sum(array_map(fn ($m) => $m->cents, $sales->hourlyRevenue($from, $to))))
        ->toBe((int) Order::where('status', '!=', 'cancelled')->sum('total_cents'));

    // Tela e exportação.
    Livewire::actingAs($gerente)->test(ReportsIndex::class, ['from' => '2026-10-07', 'to' => '2026-10-07'])
        ->set('tab', 'operation')
        ->assertSee('Tempos e entregas')->assertSee('Ana Entregadora')->assertSee('20 min')->assertSee('35 min')
        ->set('tab', 'hours')
        ->assertSee('Movimento por dia da semana e horário')->assertSee('Vendas por hora');

    $csv = $this->actingAs($gerente)->get(tenantUrl($tenant, '/relatorios/exportar/entregadores?from=2026-10-07&to=2026-10-07'))
        ->assertOk()->streamedContent();
    expect($csv)->toContain('Entregador;Entregas')->toContain('"Ana Entregadora";1;5,00')->toContain('"Bia Entregadora";1;5,00');
});
