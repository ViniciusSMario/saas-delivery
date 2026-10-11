<?php

namespace App\Modules\Delivery\Events;

use App\Modules\Access\Models\User;
use App\Modules\Orders\Models\Order;

/** A gerência mandou (ou passou) uma entrega para um entregador. */
class CourierAssigned
{
    public function __construct(public readonly Order $order, public readonly User $courier, public readonly User $actor) {}
}
