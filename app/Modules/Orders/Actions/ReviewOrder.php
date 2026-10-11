<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderReviewed;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReview;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Avaliação do cliente: só pedido concluído, até 7 dias depois, uma vez. */
class ReviewOrder
{
    /** @param  list<string>  $tags */
    public function handle(Order $order, int $rating, array $tags = [], ?string $comment = null): OrderReview
    {
        if ($order->status !== OrderStatus::Completed) {
            throw ValidationException::withMessages(['rating' => 'Dá para avaliar depois que o pedido for entregue.']);
        }

        if ($order->review()->exists()) {
            throw ValidationException::withMessages(['rating' => 'Este pedido já foi avaliado. Obrigado!']);
        }

        if (! $order->canBeReviewed()) {
            throw ValidationException::withMessages(['rating' => 'O prazo para avaliar este pedido terminou.']);
        }

        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages(['rating' => 'Escolha de 1 a 5 estrelas.']);
        }

        // Só os motivos que combinam com a nota (o que deu errado até 3; o que gostou em 4 e 5).
        $allowed = array_keys($rating <= 3 ? OrderReview::NEGATIVE_TAGS : OrderReview::POSITIVE_TAGS);
        $tags = array_values(array_unique(array_intersect($tags, $allowed)));
        $comment = trim(Str::limit(strip_tags((string) $comment), 500, '')) ?: null;

        try {
            $review = OrderReview::create([
                'order_id' => $order->id,
                'rating' => $rating,
                'tags' => $tags ?: null,
                'comment' => $comment,
                'courier_id' => $order->courier_id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Dois envios juntos (toque duplo): vale o primeiro.
            throw ValidationException::withMessages(['rating' => 'Este pedido já foi avaliado. Obrigado!']);
        }

        event(new OrderReviewed($review));

        return $review;
    }
}
