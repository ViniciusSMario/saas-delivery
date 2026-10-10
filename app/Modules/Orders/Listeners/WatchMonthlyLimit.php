<?php

namespace App\Modules\Orders\Listeners;

use App\Modules\Access\DefaultRoles;
use App\Modules\Access\Models\User;
use App\Modules\Establishment\Models\Establishment;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\MonthlyOrders;
use App\Modules\Orders\Notifications\MonthlyLimitNotice;
use App\Tenancy\TenantUrl;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** A cada pedido, confere o limite mensal do plano e avisa o dono uma vez por faixa por mês. */
class WatchMonthlyLimit
{
    public function handle(OrderPlaced $event): void
    {
        try {
            $this->check();
        } catch (Throwable $e) {
            report($e); // aviso nunca atrapalha a venda
        }
    }

    private function check(): void
    {
        $limit = MonthlyOrders::limit();

        if (! $limit) {
            return;
        }

        $count = MonthlyOrders::count();
        $percent = MonthlyOrders::percent($count);
        $month = MonthlyOrders::monthStart()->format('Y-m');
        $sent = (array) Setting::read('order_limit_notices', []);
        $sentThisMonth = (array) ($sent[$month] ?? []);

        $reached = collect(MonthlyOrders::THRESHOLDS)->filter(fn (int $t) => $percent >= $t && ! in_array($t, $sentThisMonth, true));

        if ($reached->isEmpty()) {
            return;
        }

        // Guarda só o mês atual: a chave não cresce para sempre.
        Setting::write('order_limit_notices', [$month => array_values(array_unique([...$sentThisMonth, ...$reached->all()]))]);

        $owners = User::query()->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('slug', DefaultRoles::OWNER))->get();

        Notification::send($owners, new MonthlyLimitNotice(
            $reached->max(),
            $count,
            $limit,
            Establishment::current()->trade_name ?: tenant()->name,
            TenantUrl::to(tenant(), 'assinatura'),
        ));
    }
}
