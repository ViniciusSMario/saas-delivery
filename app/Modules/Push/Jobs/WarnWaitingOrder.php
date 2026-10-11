<?php

namespace App\Modules\Push\Jobs;

use App\Modules\Orders\Models\Order;
use App\Modules\Push\StaffAlerts;
use App\Tenancy\Jobs\TenantAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Alguns minutos depois do pedido online: se ainda ninguém aceitou, avisa a gerência. */
class WarnWaitingOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, TenantAware;

    public function __construct(public int $orderId)
    {
        $this->afterCommit();
    }

    public function handle(StaffAlerts $alerts): void
    {
        if ($order = Order::find($this->orderId)) {
            $alerts->orderWaiting($order);
        }
    }
}
