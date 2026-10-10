<?php

use App\Livewire\Tenant\DataExports;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Exports\Models\DataExport;
use App\Modules\Exports\Notifications\DataExportReady;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('o dono exporta todos os dados em planilhas, sem senhas, e baixa o arquivo', function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-09 15:00', 'America/Sao_Paulo'));
    $tenant = provisionTenant('exp');
    inTenant($tenant, fn () => OpeningHour::query()->update(['is_closed' => false, 'opens_at' => '00:00', 'closes_at' => '23:59']));
    $menu = orderMenu($tenant);
    $order = placeOnline($tenant, onlineOrderInput($menu));
    $owner = createTenantUser($tenant, ['dono'], ['name' => 'Dona Exportação']);

    activateTenant($tenant);
    $this->actingAs($owner);
    Livewire::test(DataExports::class)->call('request')->assertHasNoErrors();

    $export = DataExport::sole();
    expect($export->status)->toBe('done')->and($export->size_bytes)->toBeGreaterThan(0);
    Notification::assertSentTo($owner, DataExportReady::class);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('tenant')->path($export->path));
    $customers = $zip->getFromName('planilhas/clientes.csv');
    $orders = $zip->getFromName('planilhas/pedidos.csv');
    $users = $zip->getFromName('planilhas/usuarios.csv');
    $readme = $zip->getFromName('LEIAME.txt');
    $zip->close();

    expect($customers)->toStartWith("\xEF\xBB\xBF")->toContain('Ana Cliente')
        ->and($orders)->toContain('total_reais')->toContain(number_format($order->total_cents->cents / 100, 2, ',', ''))
        ->not->toContain($order->public_token)
        ->and($users)->toContain('Dona Exportação')->not->toContain('password')
        ->and($readme)->toContain('pedidos.csv: 1');

    app(\App\Tenancy\TenantContext::class)->reset();
    $this->get(tenantUrl($tenant, '/exportar-dados/'.$export->id.'/baixar'))->assertOk()->assertDownload();
});

it('só o dono exporta e baixa', function () {
    $tenant = provisionTenant('exp2');
    $this->actingAs(createTenantUser($tenant, ['gerente']))->get(tenantUrl($tenant, '/exportar-dados'))->assertForbidden();
});
