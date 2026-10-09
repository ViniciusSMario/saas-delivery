<?php

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/** Novo pedido. Ganchos futuros: impressão na cozinha, baixa de estoque, notificação. */
class OrderPlaced
{
    use Dispatchable;

    public function __construct(public readonly Order $order)
    {
    }
}
