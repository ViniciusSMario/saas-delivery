<?php

namespace App\Modules\Printing\Listeners;

use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Printing\OrderTickets;
use Throwable;

/** Pedido novo (online, balcão ou mesa): cupons na fila das impressoras dos setores. */
class PrintNewOrder
{
    public function __construct(private readonly OrderTickets $tickets)
    {
    }

    public function handle(OrderPlaced $event): void
    {
        // Problema na impressão nunca derruba a venda.
        try {
            $this->tickets->queue($event->order);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
