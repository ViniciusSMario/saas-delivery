<?php

namespace App\Modules\Stock\Listeners;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Stock\StockLedger;

/**
 * Baixa de estoque ligada ao fluxo do pedido: quando ele entra em preparo
 * (ou vai direto para uma etapa posterior, como no fechamento de uma comanda).
 * Cancelado depois da baixa: os insumos viram perda.
 */
class SyncStockWithOrder
{
    public function __construct(private readonly StockLedger $ledger)
    {
    }

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;

        if ($order->status === OrderStatus::Cancelled) {
            $this->ledger->orderCancelled($order);

            return;
        }

        if ($order->status !== OrderStatus::Received) {
            $this->ledger->deductOrder($order);
        }
    }
}
