<?php

namespace App\Livewire\Tenant\Orders;

use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public int $orderId;

    public string $cancelReason = '';

    public bool $cancelling = false;

    public function mount(Order $order): void
    {
        $this->orderId = $order->id;
    }

    private function order(): Order
    {
        return Order::with(['items.addons', 'payments.method', 'history.user', 'createdBy'])->findOrFail($this->orderId);
    }

    public function advance(ChangeOrderStatus $change): void
    {
        $this->authorize('orders.manage');
        $order = $this->order();

        if ($next = $order->status->next($order->fulfillment)) {
            $this->attempt(fn () => $change->handle($order, $next, Auth::guard('web')->user()));
        }
    }

    public function cancel(ChangeOrderStatus $change): void
    {
        $this->authorize('orders.cancel');
        $this->validate(['cancelReason' => ['required', 'string', 'max:255']], attributes: ['cancelReason' => 'motivo']);

        $this->attempt(fn () => $change->handle($this->order(), OrderStatus::Cancelled, Auth::guard('web')->user(), $this->cancelReason));
        $this->cancelling = false;
    }

    public function markPaid(ChangeOrderStatus $change): void
    {
        $this->authorize('orders.manage');
        $this->attempt(fn () => $change->markPaid($this->order(), Auth::guard('web')->user()));
    }

    private function attempt(callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            $this->addError('status', collect($e->errors())->flatten()->first());
        }
    }

    public function render()
    {
        $order = $this->order();

        return view('livewire.tenant.orders.show', [
            'order' => $order,
            'timezone' => Setting::read('timezone', 'America/Sao_Paulo'),
            'next' => $order->status->next($order->fulfillment),
        ])->title('Pedido #'.$order->number);
    }
}
