<?php

namespace App\Modules\Tables;

use App\Modules\Access\Models\User;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Pedidos de mesa que a cozinha marcou como prontos: o garçom leva até a mesa e marca "servido". */
class ServeQueue
{
    public function __construct(private readonly ChangeOrderStatus $status)
    {
    }

    public static function query(): Builder
    {
        return Order::query()
            ->where('fulfillment', Fulfillment::Table->value)
            ->where('status', OrderStatus::Ready->value)
            ->whereNotNull('command_id');
    }

    public static function count(): int
    {
        return static::query()->count();
    }

    public function serve(Order $order, User $actor): Order
    {
        if ($order->fulfillment !== Fulfillment::Table || $order->status !== OrderStatus::Ready) {
            throw ValidationException::withMessages(['status' => "O pedido #{$order->number} não está aguardando para ser servido."]);
        }

        return $this->status->handle($order, OrderStatus::Completed, $actor, 'Servido na mesa');
    }
}
