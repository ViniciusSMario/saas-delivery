<?php

namespace App\Livewire\Tenant\Tables;

use App\Modules\Access\Models\User;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderStatusHistory;
use App\Modules\Tables\Models\Command;
use App\Modules\Tables\ServeQueue;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pedidos de mesa prontos na cozinha. O garçom toca em "Vou levar" (os outros
 * passam a ver "com Fulano") e, na mesa, em "Servido".
 */
#[Title('Prontos para servir')]
class ReadyToServe extends Component
{
    public ?string $notice = null;

    public function claim(int $orderId, ServeQueue $queue): void
    {
        $this->authorize('orders.manage');
        $this->notice = null;

        $order = $queue->claim($this->find($orderId), $this->user());
        $this->notice = "Pedido #{$order->number} é seu. Leve até a mesa e toque em “Servido”.";
        $this->dispatch('field-refresh');
    }

    public function release(int $orderId, ServeQueue $queue): void
    {
        $this->authorize('orders.manage');
        $this->notice = null;

        $order = $queue->release($this->find($orderId), $this->user());
        $this->notice = "Pedido #{$order->number} liberado para outro garçom.";
        $this->dispatch('field-refresh');
    }

    public function serve(int $orderId, ServeQueue $queue): void
    {
        $this->authorize('orders.manage');
        $this->notice = null;

        $order = $queue->serve($this->find($orderId), $this->user());
        $this->notice = "Pedido #{$order->number} servido.";
        $this->dispatch('field-refresh');
    }

    private function find(int $orderId): Order
    {
        return Order::where('fulfillment', Fulfillment::Table->value)->findOrFail($orderId);
    }

    private function user(): User
    {
        return Auth::guard('web')->user();
    }

    public function render()
    {
        $user = $this->user();

        // Hora em que ficou pronto (a reserva muda o updated_at, então vem do histórico).
        $orders = ServeQueue::query()
            ->with(['items.addons', 'runner'])
            ->addSelect(['ready_at' => OrderStatusHistory::select('created_at')->whereColumn('order_id', 'orders.id')->where('to_status', 'ready')->latest('id')->limit(1)])
            ->withCasts(['ready_at' => 'datetime'])
            ->get()
            // Primeiro os meus, depois os livres, por último os que estão com outro garçom.
            ->sortBy(fn (Order $o) => [$o->runner_id === $user->id ? 0 : ($o->runner_id ? 2 : 1), $o->ready_at?->getTimestamp() ?? 0])
            ->values();

        return view('livewire.tenant.tables.ready-to-serve', [
            'orders' => $orders,
            'me' => $user,
            'canOverride' => ServeQueue::canOverride($user),
            'commands' => Command::with('diningTable')->whereIn('id', $orders->pluck('command_id'))->get()->keyBy('id'),
        ]);
    }
}
