<?php

namespace App\Modules\Messaging\Listeners;

use App\Modules\Messaging\OrderMessages;
use App\Modules\Messaging\WhatsApp;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderStatusChanged;
use Throwable;

/** Pedido novo ou mudança de situação: aviso ao cliente pelo WhatsApp (se ligado). */
class NotifyCustomer
{
    public function __construct(private readonly WhatsApp $whatsapp)
    {
    }

    public function handle(OrderPlaced|OrderStatusChanged $event): void
    {
        $order = $event->order;
        $type = OrderMessages::eventFor($order);

        if (! $type) {
            return;
        }

        // Aviso que falha nunca atrapalha o pedido.
        try {
            $this->whatsapp->notify($order, $type);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
