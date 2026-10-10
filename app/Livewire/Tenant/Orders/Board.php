<?php

namespace App\Livewire\Tenant\Orders;

use App\Modules\Access\Models\User;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Printing\OrderTickets;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Painel da cozinha/atendimento: pedidos em andamento por status.
 * Atualiza por wire:poll (o documento deixa Reverb para quando o polling não bastar).
 */
#[Layout('components.layouts.app', ['wide' => true])]
#[Title('Painel de pedidos')]
class Board extends Component
{
    public int $lastSeenId = 0;

    public ?int $cancellingId = null;

    public string $cancelReason = '';

    public function mount(): void
    {
        $this->lastSeenId = (int) Order::max('id');
    }

    public function advance(int $orderId, ChangeOrderStatus $change): void
    {
        $this->authorize('orders.manage');

        $order = Order::findOrFail($orderId);
        $next = $order->status->next($order->fulfillment);

        if (! $next) {
            return;
        }

        try {
            $change->handle($order, $next, $this->user());
        } catch (ValidationException $e) {
            // Outro atendente mexeu no pedido antes: o painel só recarrega.
            $this->addError('board', collect($e->errors())->flatten()->first());
        }
    }

    /** Manda o pedido de novo para as impressoras dos setores. */
    public function reprint(int $orderId, OrderTickets $tickets): void
    {
        $this->authorize('orders.view');

        $count = $tickets->queue(Order::findOrFail($orderId), reprint: true)->count();
        $this->dispatch('notify', message: $count ? 'Enviado para a impressão.' : 'Nenhuma impressora configurada.');
    }

    public function startCancel(int $orderId): void
    {
        $this->authorize('orders.cancel');
        $this->cancellingId = $orderId;
        $this->cancelReason = '';
        $this->resetErrorBag();
    }

    public function cancel(ChangeOrderStatus $change): void
    {
        $this->authorize('orders.cancel');
        $this->validate(['cancelReason' => ['required', 'string', 'max:255']], attributes: ['cancelReason' => 'motivo']);

        $change->handle(Order::findOrFail($this->cancellingId), OrderStatus::Cancelled, $this->user(), $this->cancelReason);
        $this->reset('cancellingId', 'cancelReason');
    }

    private function user(): User
    {
        return Auth::guard('web')->user();
    }

    public function render()
    {
        $orders = Order::open()->with(['items.addons', 'courier'])->oldest()->get()->groupBy(fn (Order $o) => $o->status->value);
        $newest = (int) Order::max('id');

        // Pedido novo desde a última atualização: o navegador toca o aviso.
        if ($newest > $this->lastSeenId) {
            $this->dispatch('new-order', count: Order::where('id', '>', $this->lastSeenId)->count());
            $this->lastSeenId = $newest;
        }

        return view('livewire.tenant.orders.board', [
            'columns' => OrderStatus::active(),
            'orders' => $orders,
            'todayDone' => Order::whereIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])
                ->where('updated_at', '>=', now()->startOfDay())->count(),
        ]);
    }
}
