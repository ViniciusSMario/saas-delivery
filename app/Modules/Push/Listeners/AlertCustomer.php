<?php

namespace App\Modules\Push\Listeners;

use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Push\CustomerAlerts;
use Throwable;

/** O pedido andou: aviso no celular do cliente que pediu para acompanhar. */
class AlertCustomer
{
    public function __construct(private readonly CustomerAlerts $alerts) {}

    public function handle(OrderStatusChanged $event): void
    {
        // Aviso que falha nunca atrapalha o pedido.
        try {
            $this->alerts->statusChanged($event->order);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
