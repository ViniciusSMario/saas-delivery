<?php

namespace App\Modules\Promotions\Listeners;

use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Promotions\Loyalty;
use Throwable;

/** Pedido concluído: confere o cartão fidelidade do cliente e gera o prêmio quando completa. */
class AwardLoyalty
{
    public function __construct(private readonly Loyalty $loyalty) {}

    public function handle(OrderStatusChanged $event): void
    {
        // Falha na fidelidade nunca atrapalha a conclusão do pedido.
        try {
            $this->loyalty->award($event->order);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
