<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use App\Modules\Cash\CashLedger;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Models\Order;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Avança ou cancela um pedido. A permissão (orders.manage / orders.cancel) é
 * conferida por quem chama; aqui ficam as regras do fluxo.
 */
class ChangeOrderStatus
{
    public function __construct(private readonly CashLedger $ledger)
    {
    }

    /**
     * @param  (Closure(Order): void)|null  $guard  regra extra conferida com a linha travada (ex.: "o pedido ainda é seu"); lança ValidationException para recusar
     * @param  array<string, mixed>  $attributes  gravados junto com o status, na mesma transação (ex.: entregador e hora de saída)
     */
    public function handle(Order $order, OrderStatus $to, ?User $actor = null, ?string $reason = null, ?Closure $guard = null, array $attributes = []): Order
    {
        $reason = trim((string) $reason) ?: null;

        DB::connection('tenant')->transaction(function () use ($order, $to, $actor, $reason, $guard, $attributes) {
            // Trava a linha: dois atendentes clicando ao mesmo tempo não pulam etapas.
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $from = $locked->status;

            if ($guard) {
                $guard($locked);
            }

            if (! $from->canTransitionTo($to, $locked->fulfillment)) {
                throw ValidationException::withMessages([
                    'status' => "O pedido #{$locked->number} está \"{$from->label()}\" e não pode ir para \"{$to->label()}\".",
                ]);
            }

            if ($to === OrderStatus::Cancelled && ! $reason) {
                throw ValidationException::withMessages(['reason' => 'Informe o motivo do cancelamento.']);
            }

            $locked->fill($attributes);
            $locked->status = $to;

            if ($to === OrderStatus::Completed) {
                $locked->completed_at = now();
                // Entrega/retirada: o pagamento é recebido na conclusão e entra no caixa aberto.
                $this->receivePending($locked, $actor);
            }

            if ($to === OrderStatus::Cancelled) {
                $locked->cancelled_at = now();
                $locked->cancel_reason = mb_substr($reason, 0, 255);

                foreach ($locked->payments()->where('status', PaymentStatus::Paid)->get() as $payment) {
                    $this->ledger->refundOrderPayment($payment, $actor);
                    $payment->update(['status' => PaymentStatus::Refunded]);
                }

                Audit::record('order.cancelled', $locked, ['numero' => $locked->number, 'motivo' => $reason], $actor);
            }

            $locked->save();
            $locked->history()->create(['from_status' => $from, 'to_status' => $to, 'user_id' => $actor?->id, 'note' => $reason]);

            $order->setRawAttributes($locked->getAttributes(), true);
        });

        event(new OrderStatusChanged($order));

        return $order;
    }

    public function markPaid(Order $order, ?User $actor = null): void
    {
        if ($order->status === OrderStatus::Cancelled) {
            throw ValidationException::withMessages(['status' => 'Pedido cancelado não pode ser marcado como pago.']);
        }

        $updated = DB::connection('tenant')->transaction(fn () => $this->receivePending($order, $actor));

        if ($updated) {
            Audit::record('order.paid', $order, ['numero' => $order->number], $actor);
        }
    }

    private function receivePending(Order $order, ?User $actor): int
    {
        $pending = $order->payments()->where('status', PaymentStatus::Pending)->with('method')->get();

        foreach ($pending as $payment) {
            $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
            $this->ledger->recordOrderPayment($payment, $actor);
        }

        return $pending->count();
    }
}
