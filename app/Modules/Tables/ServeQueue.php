<?php

namespace App\Modules\Tables;

use App\Modules\Access\Models\User;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pedidos de mesa que a cozinha marcou como prontos. O garçom toca em "Vou levar"
 * (reserva: os outros veem "com Fulano" e não vão buscar o mesmo prato) e, na mesa,
 * em "Servido". Toda decisão é tomada com a linha do pedido travada; toque duplo
 * conta como um só. A gerência (painel completo) pode soltar a reserva de outro.
 */
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

    /** Prontos que este garçom pode levar: livres ou já reservados por ele. */
    public static function availableFor(User $user): Builder
    {
        return static::query()->where(fn ($q) => $q->whereNull('runner_id')->orWhere('runner_id', $user->id));
    }

    public static function countFor(User $user): int
    {
        return static::availableFor($user)->count();
    }

    /** Quem está no painel completo (gerência) pode soltar ou concluir a reserva de outro garçom. */
    public static function canOverride(User $user): bool
    {
        return ! $user->usesFieldMode();
    }

    public function claim(Order $order, User $actor): Order
    {
        return $this->locked($order, function (Order $locked) use ($actor) {
            $this->assertWaiting($locked);

            if ($locked->runner_id === $actor->id) {
                return; // toque duplo: já é dele
            }

            if ($locked->runner_id) {
                throw ValidationException::withMessages(['status' => $this->runnerName($locked)." já vai levar o pedido #{$locked->number}."]);
            }

            $locked->update(['runner_id' => $actor->id, 'runner_claimed_at' => now()]);
        });
    }

    public function release(Order $order, User $actor): Order
    {
        return $this->locked($order, function (Order $locked) use ($actor) {
            if (! $locked->runner_id) {
                return;
            }

            if ($locked->runner_id !== $actor->id && ! static::canOverride($actor)) {
                throw ValidationException::withMessages(['status' => "O pedido #{$locked->number} está com ".$this->runnerName($locked).'.']);
            }

            $locked->update(['runner_id' => null, 'runner_claimed_at' => null]);
        });
    }

    public function serve(Order $order, User $actor): Order
    {
        if ($order->fulfillment !== Fulfillment::Table) {
            throw ValidationException::withMessages(['status' => 'Este pedido não é de mesa.']);
        }

        try {
            return $this->status->handle($order, OrderStatus::Completed, $actor, 'Servido na mesa', guard: function (Order $locked) use ($actor) {
                if ($locked->status === OrderStatus::Ready && $locked->runner_id && $locked->runner_id !== $actor->id && ! static::canOverride($actor)) {
                    throw ValidationException::withMessages(['status' => "O pedido #{$locked->number} está com ".$this->runnerName($locked).': só quem vai levar marca como servido.']);
                }
            });
        } catch (ValidationException $e) {
            $order->refresh();

            if ($order->status === OrderStatus::Completed) {
                // Toque duplo de quem serviu: já está feito.
                if ($order->history()->reorder('id', 'desc')->value('user_id') === $actor->id) {
                    return $order;
                }

                throw $this->alreadyServed($order);
            }

            if ($order->status === OrderStatus::Cancelled) {
                throw ValidationException::withMessages(['status' => "O pedido #{$order->number} foi cancelado."]);
            }

            if ($order->status !== OrderStatus::Ready) {
                throw ValidationException::withMessages(['status' => "O pedido #{$order->number} ainda não está pronto ({$order->status->label()})."]);
            }

            throw $e;
        }
    }

    private function assertWaiting(Order $locked): void
    {
        if ($locked->fulfillment !== Fulfillment::Table) {
            throw ValidationException::withMessages(['status' => 'Este pedido não é de mesa.']);
        }

        if ($locked->status === OrderStatus::Completed) {
            throw $this->alreadyServed($locked);
        }

        if ($locked->status !== OrderStatus::Ready) {
            throw ValidationException::withMessages(['status' => "O pedido #{$locked->number} não está pronto ({$locked->status->label()})."]);
        }
    }

    private function alreadyServed(Order $order): ValidationException
    {
        $by = $order->lastActorName();

        return ValidationException::withMessages(['status' => "O pedido #{$order->number} já foi servido".($by ? " por {$by}." : '.')]);
    }

    private function runnerName(Order $order): string
    {
        return User::find($order->runner_id)?->name ?? 'Outro garçom';
    }

    /** @param  Closure(Order): void  $callback */
    private function locked(Order $order, Closure $callback): Order
    {
        DB::connection('tenant')->transaction(function () use ($order, $callback) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $callback($locked);
            $order->setRawAttributes($locked->getAttributes(), true);
        });

        return $order;
    }
}
