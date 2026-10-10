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
 *
 * Concorrência: toda decisão é tomada com a linha do pedido travada. Dois entregadores
 * tocando juntos: só o primeiro leva, o outro recebe "Fulano já pegou". Toque duplo
 * do mesmo entregador conta como um só.
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

        return $order->status === OrderStatus::Dispatched
            ? $this->reassign($order, $courier, $actor)
            : $this->dispatch($order, $courier, $actor);
    }

    public function complete(Order $order, User $actor): Order
    {
        $this->assertDelivery($order);
        $manager = $actor->hasPermission(Permission::OrdersManage);

        try {
            return $this->status->handle($order, OrderStatus::Completed, $actor, guard: function (Order $locked) use ($actor, $manager) {
                if ($locked->status === OrderStatus::Dispatched && $locked->courier_id !== $actor->id && ! $manager) {
                    throw ValidationException::withMessages(['status' => "O pedido #{$locked->number} está com ".$this->courierName($locked).'.']);
                }
            });
        } catch (ValidationException $e) {
            $order->refresh();

            if ($order->status !== OrderStatus::Completed) {
                throw $e;
            }

            // Toque duplo: já está entregue (o pagamento entrou no caixa uma vez só).
            if ($order->courier_id === $actor->id) {
                return $order;
            }

            $by = $order->lastActorName();

            throw ValidationException::withMessages(['status' => "O pedido #{$order->number} já foi entregue".($by ? " ({$by})." : '.')]);
        }
    }

    /** Um passo só: entregador, hora de saída e "saiu para entrega" mudam juntos, ou nada muda. */
    private function dispatch(Order $order, User $courier, User $actor): Order
    {
        $this->assertDelivery($order);

        try {
            return $this->status->handle(
                $order,
                OrderStatus::Dispatched,
                $actor,
                guard: function (Order $locked) use ($courier) {
                    if ($locked->status === OrderStatus::Dispatched && $locked->courier_id !== $courier->id) {
                        throw ValidationException::withMessages(['status' => $this->courierName($locked)." já pegou o pedido #{$locked->number}."]);
                    }
                },
                attributes: ['courier_id' => $courier->id, 'dispatched_at' => now()],
            );
        } catch (ValidationException $e) {
            $order->refresh();

            if ($order->status === OrderStatus::Dispatched) {
                // Toque duplo do mesmo entregador: já está com ele.
                if ($order->courier_id === $courier->id) {
                    return $order;
                }

                throw ValidationException::withMessages(['status' => $this->courierName($order)." já pegou o pedido #{$order->number}."]);
            }

            throw ValidationException::withMessages(['status' => "O pedido #{$order->number} não está mais disponível para sair ({$order->status->label()})."]);
        }
    }

    /** Troca o entregador de um pedido que já saiu (com a linha travada, para não cruzar com "Entregue"). */
    private function reassign(Order $order, User $courier, User $actor): Order
    {
        $previous = DB::connection('tenant')->transaction(function () use ($order, $courier) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== OrderStatus::Dispatched) {
                throw ValidationException::withMessages(['status' => "O pedido #{$locked->number} não está mais na rua ({$locked->status->label()})."]);
            }

            $previous = $this->courierName($locked);
            $locked->update(['courier_id' => $courier->id]);

            return $previous;
        });

        Audit::record('delivery.reassigned', $order, ['numero' => $order->number, 'de' => $previous, 'para' => $courier->name], $actor);

        return $order->refresh();
    }

    private function courierName(Order $order): string
    {
        return ($order->courier_id ? User::find($order->courier_id)?->name : null) ?? 'Outro entregador';
    }

    private function assertDelivery(Order $order): void
    {
        if ($order->fulfillment !== Fulfillment::Delivery) {
            throw ValidationException::withMessages(['status' => 'Este pedido não é de entrega.']);
        }
    }
}
