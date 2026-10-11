<?php

namespace App\Livewire\Tenant;

use App\Modules\Access\Models\User;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Pedido do cardápio online esperando aceite, em qualquer tela do painel: cartão com
 * "Aceitar" e som repetido até alguém aceitar (resources/js/alarm.js). No painel de
 * pedidos o cartão some (o pedido já está na coluna Recebido), mas o som continua.
 */
class OrderAlarm extends Component
{
    public const SHOWN = 4;

    public bool $onBoard = false;

    public function mount(): void
    {
        $this->onBoard = request()->routeIs('orders.board');
    }

    /** Aceitar = iniciar o preparo. Se outra pessoa aceitou antes, o cartão só atualiza. */
    public function accept(int $orderId, ChangeOrderStatus $change): void
    {
        $this->authorize('orders.manage');

        $order = $this->waiting()->find($orderId);

        if (! $order) {
            return;
        }

        try {
            // A transição é conferida com a linha travada: dois cliques juntos aceitam uma vez só.
            $change->handle($order, OrderStatus::Preparing, $this->user());
        } catch (ValidationException) {
            // Já aceito (ou cancelado) por outra pessoa: o cartão só atualiza.
        }
    }

    private function waiting(): Builder
    {
        return Order::query()->where('source', OrderSource::Online->value)->where('status', OrderStatus::Received->value);
    }

    private function user(): User
    {
        return Auth::guard('web')->user();
    }

    public function render()
    {
        $orders = $this->waiting()->withSum('items as items_count', 'quantity')->oldest()->limit(self::SHOWN)->get();
        $count = $orders->count() < self::SHOWN ? $orders->count() : $this->waiting()->count();

        $this->dispatch('order-alarm', waiting: $count, newest: (int) $this->waiting()->max('id'));

        return view('livewire.tenant.order-alarm', ['orders' => $orders, 'count' => $count]);
    }
}
