<?php

namespace App\Modules\Push\Listeners;

use App\Modules\Delivery\Events\CourierAssigned;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Push\StaffAlerts;
use Throwable;

/** Pedido novo, pedido pronto ou entrega mandada para alguém: aviso no celular da equipe. */
class AlertStaff
{
    public function __construct(private readonly StaffAlerts $alerts) {}

    public function handle(OrderPlaced|OrderStatusChanged|CourierAssigned $event): void
    {
        // Aviso que falha nunca atrapalha o pedido.
        try {
            match (true) {
                $event instanceof OrderPlaced => $this->alerts->orderPlaced($event->order),
                $event instanceof CourierAssigned => $this->alerts->courierAssigned($event->order, $event->courier),
                default => $this->alerts->orderReady($event->order),
            };
        } catch (Throwable $e) {
            report($e);
        }
    }
}
