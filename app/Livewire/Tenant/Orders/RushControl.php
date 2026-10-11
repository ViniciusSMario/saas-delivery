<?php

namespace App\Livewire\Tenant\Orders;

use App\Modules\Access\Models\User;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\OrderEta;
use App\Modules\Orders\RushMode;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Hora do sufoco, no painel de pedidos: aumentar a previsão que o cliente vê ou pausar os
 * pedidos online por alguns minutos. Os dois voltam ao normal sozinhos (RushMode).
 */
class RushControl extends Component
{
    public function delay(int $minutes): void
    {
        $this->authorize('orders.manage');

        if (in_array($minutes, RushMode::DELAY_OPTIONS, true)) {
            RushMode::delay($minutes, $this->user());
        }
    }

    public function clearDelay(): void
    {
        $this->authorize('orders.manage');
        RushMode::clearDelay($this->user());
    }

    public function pause(int $minutes): void
    {
        $this->authorize('orders.manage');

        if (in_array($minutes, RushMode::PAUSE_OPTIONS, true)) {
            RushMode::pause($minutes, $this->user());
        }
    }

    public function resume(): void
    {
        $this->authorize('orders.manage');
        RushMode::resume($this->user());
    }

    private function user(): User
    {
        return Auth::guard('web')->user();
    }

    public function render()
    {
        $acceptsOnline = (bool) Setting::read('accepts_online_orders', true);

        return view('livewire.tenant.orders.rush-control', [
            'extra' => RushMode::extraMinutes(),
            'delayUntil' => RushMode::delayUntil(),
            'pausedUntil' => $acceptsOnline ? RushMode::pausedUntil() : null,
            'acceptsOnline' => $acceptsOnline,
            'acceptsDelivery' => (bool) Setting::read('accepts_delivery', true),
            'acceptsPickup' => (bool) Setting::read('accepts_pickup', true),
            'etaDelivery' => OrderEta::now(Fulfillment::Delivery),
            'etaPickup' => OrderEta::now(Fulfillment::Pickup),
            'timezone' => Setting::read('timezone', 'America/Sao_Paulo'),
        ]);
    }
}
