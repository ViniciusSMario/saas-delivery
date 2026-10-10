<?php

use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Notifications\MonthlyLimitNotice;
use App\Platform\Models\Plan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 15:00', 'America/Sao_Paulo'));
    $this->tenant = provisionTenant('lim');
    inTenant($this->tenant, fn () => OpeningHour::query()->update(['is_closed' => false, 'opens_at' => '00:00', 'closes_at' => '23:59']));
    $plan = Plan::create(['slug' => 'teste-'.Str::random(5), 'name' => 'Teste', 'price_cents' => 100, 'limits' => ['orders_per_month' => 5], 'features' => [], 'is_public' => false]);
    $this->tenant->update(['plan_id' => $plan->id]);
    $this->menu = orderMenu($this->tenant);
});

it('avisa o dono a 80% e a 100% do limite do mês, uma vez cada, sem bloquear pedidos', function () {
    Notification::fake();
    $owner = createTenantUser($this->tenant, ['dono']);
    createTenantUser($this->tenant, ['caixa']);

    foreach (range(1, 3) as $i) {
        placeOnline($this->tenant, onlineOrderInput($this->menu));
    }
    Notification::assertNothingSent();

    placeOnline($this->tenant, onlineOrderInput($this->menu)); // 4 de 5 = 80%
    Notification::assertSentTo($owner, MonthlyLimitNotice::class, fn ($n) => $n->percent === 80 && $n->count === 4);

    placeOnline($this->tenant, onlineOrderInput($this->menu)); // 100%
    $sixth = placeOnline($this->tenant, onlineOrderInput($this->menu)); // acima: continua vendendo

    expect($sixth->exists)->toBeTrue();
    Notification::assertSentToTimes($owner, MonthlyLimitNotice::class, 2);
    Notification::assertSentTo($owner, MonthlyLimitNotice::class, fn ($n) => $n->percent === 100);

    // O painel do dono mostra o aviso.
    $this->actingAs($owner)->get(tenantUrl($this->tenant, '/'))->assertSee('6 de 5 pedidos do mês')->assertSee('passou do limite');

    // Mês seguinte: avisos zerados.
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Sao_Paulo'));
    foreach (range(1, 4) as $i) {
        placeOnline($this->tenant, onlineOrderInput($this->menu));
    }
    Notification::assertSentToTimes($owner, MonthlyLimitNotice::class, 3);
    expect(array_keys(inTenant($this->tenant, fn () => Setting::read('order_limit_notices'))))->toBe(['2026-11']);
});

it('estabelecimento sem plano (cortesia) não tem limite nem aviso', function () {
    Notification::fake();
    $this->tenant->update(['plan_id' => null]);
    createTenantUser($this->tenant, ['dono']);

    foreach (range(1, 6) as $i) {
        placeOnline($this->tenant, onlineOrderInput($this->menu));
    }

    Notification::assertNothingSent();
});
