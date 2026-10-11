<?php

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Models\OrderReview;
use Illuminate\Foundation\Events\Dispatchable;

/** O cliente avaliou o pedido (nota baixa avisa a gerência). */
class OrderReviewed
{
    use Dispatchable;

    public function __construct(public readonly OrderReview $review) {}
}
