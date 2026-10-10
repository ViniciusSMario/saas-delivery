<?php

use App\Livewire\Tenant\Orders\Board;
use App\Livewire\Tenant\Printing\Settings;
use App\Livewire\Tenant\Printing\Station;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Menu\Models\Category;
use App\Modules\Printing\EscPos;
use App\Modules\Printing\Models\PrintJob;
use App\Modules\Printing\Models\PrintStation;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 15:00', 'America/Sao_Paulo'));
    $this->tenant = provisionTenant('prt');
    inTenant($this->tenant, fn () => OpeningHour::query()->update(['is_closed' => false, 'opens_at' => '00:00', 'closes_at' => '23:59']));
    $this->menu = orderMenu($this->tenant);
});

/** Cozinha (padrão), Bar (bebidas) e Expedição (pedido completo). */
function kitchenSetup(array $menu): array
{
    $kitchen = PrintStation::create(['name' => 'Cozinha', 'is_default' => true]);
    $bar = PrintStation::create(['name' => 'Bar']);
    $dispatch = PrintStation::create(['name' => 'Expedição', 'prints_full_order' => true]);

    $drinks = Category::create(['name' => 'Bebidas', 'print_station_id' => $bar->id]);
    $menu['soda']->update(['category_id' => $drinks->id]);

    return compact('kitchen', 'bar', 'dispatch');
}

function ticketText(PrintJob $job): string
{
    return collect($job->lines)->pluck('text')->implode("\n");
}

it('separa os itens do pedido por setor e manda o pedido completo para a expedição', function () {
    $stations = inTenant($this->tenant, fn () => kitchenSetup($this->menu));

    $order = placeOnline($this->tenant, onlineOrderInput($this->menu, ['lines' => [
        ['type' => 'product', 'id' => $this->menu['pizza']->id, 'quantity' => 2, 'addons' => [$this->menu['cheese']->id], 'notes' => 'sem cebola'],
        ['type' => 'product', 'id' => $this->menu['soda']->id, 'quantity' => 1],
        ['type' => 'combo', 'id' => $this->menu['combo']->id, 'quantity' => 1],
    ]]));

    inTenant($this->tenant, function () use ($stations, $order) {
        $jobs = PrintJob::where('order_id', $order->id)->get()->keyBy('print_station_id');
        expect($jobs)->toHaveCount(3);

        $kitchen = ticketText($jobs[$stations['kitchen']->id]);
        expect($kitchen)->toContain('PEDIDO #'.$order->number)
            ->toContain('2x '.$this->menu['pizza']->name)
            ->toContain('+ Catupiry')
            ->toContain('OBS: sem cebola')
            ->toContain('- 1x '.$this->menu['pizza']->name)      // a pizza do combo
            ->not->toContain('1x '.$this->menu['soda']->name);     // refri é do bar

        $bar = ticketText($jobs[$stations['bar']->id]);
        expect($bar)->toContain('1x '.$this->menu['soda']->name)
            ->toContain('- 1x '.$this->menu['soda']->name)          // o refri do combo
            ->not->toContain('2x '.$this->menu['pizza']->name);

        $receipt = $jobs[$stations['dispatch']->id];
        expect($receipt->kind)->toBe('receipt')
            ->and(ticketText($receipt))->toContain('TOTAL')->toContain($order->total_cents->format())->toContain('Ana Cliente');
    });
});

it('não imprime nada sem setores e nunca derruba a venda', function () {
    $order = placeOnline($this->tenant, onlineOrderInput($this->menu));

    expect($order->exists)->toBeTrue()
        ->and(inTenant($this->tenant, fn () => PrintJob::count()))->toBe(0);
});

it('a estação de impressão pega cada cupom uma vez, confirma e reimprime', function () {
    $stations = inTenant($this->tenant, fn () => kitchenSetup($this->menu));
    placeOnline($this->tenant, onlineOrderInput($this->menu));

    activateTenant($this->tenant);
    $this->actingAs(createTenantUser($this->tenant, ['cozinha']));

    $component = Livewire::test(Station::class)->set('stationIds', [$stations['kitchen']->id]);
    $first = $component->instance()->claim();
    expect($first['ids'])->toHaveCount(1)->and($first['html'])->toContain('ticket-big');

    // Outra estação (ou o próximo ciclo) não pega o mesmo cupom.
    expect($component->instance()->claim()['ids'])->toBe([]);

    $component->call('confirm', $first['ids']);
    $job = PrintJob::find($first['ids'][0]);
    expect($job->status)->toBe('printed')->and($job->printed_at)->not->toBeNull();

    $component->call('reprint', $job->id);
    expect($job->refresh()->status)->toBe('pending');

    // Estação que sumiu no meio da impressão: o cupom volta para a fila depois de 2 minutos.
    $component->instance()->claim();
    $this->travel(3)->minutes();
    expect($component->instance()->claim()['ids'])->toBe([$job->id]);
});

it('envia ESC/POS para a impressora de rede quando a instalação permite', function () {
    config(['printing.network_enabled' => true]);
    $server = stream_socket_server('tcp://127.0.0.1:9107', $errno, $error);
    expect($server)->not->toBeFalse();

    inTenant($this->tenant, fn () => PrintStation::create(['name' => 'Cozinha', 'mode' => 'network', 'host' => '127.0.0.1', 'port' => 9107, 'is_default' => true, 'copies' => 2]));
    $order = placeOnline($this->tenant, onlineOrderInput($this->menu));

    $client = stream_socket_accept($server, 2);
    $bytes = stream_get_contents($client);
    fclose($client);
    fclose($server);

    expect($bytes)->toStartWith("\x1B@")
        ->toContain('PEDIDO #'.$order->number)
        ->and(substr_count($bytes, "\x1DV"))->toBe(2) // duas vias, dois cortes
        ->and(inTenant($this->tenant, fn () => PrintJob::sole()->status))->toBe('printed');
});

it('marca o cupom como falho quando a impressora de rede não responde', function () {
    config(['printing.network_enabled' => true, 'printing.timeout' => 0.5]);
    inTenant($this->tenant, fn () => PrintStation::create(['name' => 'Bar', 'mode' => 'network', 'host' => '127.0.0.1', 'port' => 9109, 'is_default' => true]));

    $order = placeOnline($this->tenant, onlineOrderInput($this->menu));

    expect($order->exists)->toBeTrue();
    $job = inTenant($this->tenant, fn () => PrintJob::sole());
    expect($job->status)->toBeIn(['pending', 'failed'])->and($job->error)->toContain('não respondeu');
});

it('converte o cupom em ESC/POS sem acentos, com negrito e letra dupla', function () {
    $bytes = EscPos::encode([['text' => 'Pão de queijo', 'style' => 'big', 'align' => 'center']]);

    expect($bytes)->toContain('Pao de queijo')->toContain("\x1Ba\x01")->toContain("\x1D!\x11")->toEndWith("\x1DV\x42\x00");
});

it('configura setores, categorias e teste de impressão', function () {
    activateTenant($this->tenant);
    $this->actingAs(createTenantUser($this->tenant, ['dono']));

    Livewire::test(Settings::class)
        ->set('name', 'Cozinha')->call('save')->assertHasNoErrors()
        ->set('name', 'Bar')->set('mode', 'network')->set('host', '192.168.0.50')->call('save')->assertHasErrors('mode')
        ->set('mode', 'browser')->call('save')->assertHasNoErrors();

    $kitchen = PrintStation::where('name', 'Cozinha')->sole();
    $bar = PrintStation::where('name', 'Bar')->sole();
    expect($kitchen->is_default)->toBeTrue()->and($bar->is_default)->toBeFalse();

    config(['printing.network_enabled' => true]);
    Livewire::test(Settings::class)->set('name', 'Rede')->set('mode', 'network')->set('host', 'redis')->set('port', 6379)->call('save')->assertHasErrors('port');

    Livewire::test(Settings::class)
        ->set("routing.{$this->menu['category']->id}", (string) $bar->id)
        ->call('test', $kitchen->id);

    expect($this->menu['category']->fresh()->print_station_id)->toBe($bar->id)
        ->and(PrintJob::where('kind', 'test')->where('print_station_id', $kitchen->id)->exists())->toBeTrue();
});

it('reimprime pelo painel de pedidos', function () {
    inTenant($this->tenant, fn () => PrintStation::create(['name' => 'Cozinha', 'is_default' => true]));
    $order = placeOnline($this->tenant, onlineOrderInput($this->menu));

    activateTenant($this->tenant);
    $this->actingAs(createTenantUser($this->tenant, ['cozinha']));
    Livewire::test(Board::class)->call('reprint', $order->id)->assertDispatched('notify');

    expect(PrintJob::where('order_id', $order->id)->count())->toBe(2)
        ->and(ticketText(PrintJob::latest('id')->first()))->toContain('REIMPRESSÃO');
});
