<?php

namespace App\Livewire\Tenant\Tables;

use App\Modules\Orders\Models\Order;
use App\Modules\Tables\Models\Command;
use App\Modules\Tables\ServeQueue;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Pedidos de mesa prontos na cozinha: o garçom leva e toca em "Servido". */
#[Title('Prontos para servir')]
class ReadyToServe extends Component
{
    public function serve(int $orderId, ServeQueue $queue): void
    {
        $this->authorize('orders.manage');

        $queue->serve(ServeQueue::query()->findOrFail($orderId), Auth::guard('web')->user());
        $this->dispatch('field-refresh');
    }

    public function render()
    {
        $orders = ServeQueue::query()->with('items.addons')->orderBy('updated_at')->get();

        return view('livewire.tenant.tables.ready-to-serve', [
            'orders' => $orders,
            'commands' => Command::with('diningTable')->whereIn('id', $orders->pluck('command_id'))->get()->keyBy('id'),
        ]);
    }
}
