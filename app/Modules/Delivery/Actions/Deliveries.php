<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Permission;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saída e conclusão das entregas. O entregador "pega" um pedido pronto (sai para
 * entrega com ele) e conclui ao entregar; o pagamento na entrega entra no caixa aberto.
 */
class Deliveries
{
    public function __construct(private readonly ChangeOrderStatus $status)
    {
    }

    /** O entregador pega o pedido pronto: fica com ele e sai para a entrega. */
    public function take(Order $order, User $courier): Order
    {
        return $this->dispatch($order, $courier, $courier);
    }

    /** Gerência escolhe (ou troca) o entregador do pedido. */
    public function assign(Order $order, User $courier, User $actor): Order
    {
        if (! $courier->is_active || ! $courier->hasPermission(Permission::DeliveriesOperate)) {
            throw ValidationException::withMessages(['courier' => "{$courier->name} não faz entregas (perfil sem a permissão de entregas)."]);
        }

        if ($order->status === OrderStatus::Dispatched) {
            $previous = $order->courier?->name;
            $order->update(['courier_id' => $courier->id]);
            Audit::record('delivery.reassigned', $order, ['numero' => $order->number, 'de' => $previous, 'para' => $courier->name], $actor);

            return $order;
        }

        return $this->dispatch($order, $courier, $actor);
    }

    public function complete(Order $order, User $actor): Order
    {
        $this->assertDelivery($order);

        if ($order->courier_id !== $actor->id && ! $actor->hasPermission(Permission::OrdersManage)) {
            throw ValidationException::withMessages(['status' => 'Esta entrega está com outro entregador.']);
        }

        return $this->status->handle($order, OrderStatus::Completed, $actor);
    }

    private function dispatch(Order $order, User $courier, User $actor): Order
    {
        $this->assertDelivery($order);

        DB::connection('tenant')->transaction(function () use ($order, $courier) {
            // Dois entregadores tocando no mesmo pedido: só o primeiro leva.
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== OrderStatus::Ready || $locked->courier_id) {
                throw ValidationException::withMessages(['status' => "O pedido #{$locked->number} não está mais disponível para sair."]);
            }

            $locked->update(['courier_id' => $courier->id, 'dispatched_at' => now()]);
        });

        $this->status->handle($order->refresh(), OrderStatus::Dispatched, $actor);

        return $order->refresh();
    }

    private function assertDelivery(Order $order): void
    {
        if ($order->fulfillment !== Fulfillment::Delivery) {
            throw ValidationException::withMessages(['status' => 'Este pedido não é de entrega.']);
        }
    }
}
