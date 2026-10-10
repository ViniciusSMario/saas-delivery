<?php

namespace App\Livewire\Tenant;

use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Tables\ServeQueue;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Abas de baixo do "modo app". Atualiza sozinha: o número de pedidos prontos
 * aparece na aba e o celular vibra quando chega um novo.
 */
class FieldNav extends Component
{
    #[Locked]
    public string $current = '';

    public int $toServe = 0;

    public int $toDeliver = 0;

    public function mount(): void
    {
        $this->current = (string) request()->route()?->getName();
        [$this->toServe, $this->toDeliver] = $this->counts();
    }

    /** @return array{0: int, 1: int} */
    private function counts(): array
    {
        $user = Auth::guard('web')->user();

        return [
            // Garçom: só o que ele pode levar (livre ou reservado por ele).
            $user->can('tables.manage') ? ServeQueue::countFor($user) : 0,
            $user->can('deliveries.operate')
                ? Order::where('fulfillment', Fulfillment::Delivery->value)->where('status', OrderStatus::Ready->value)->count()
                : 0,
        ];
    }

    /** Também chamado na hora pelas telas (pegou, entregou, serviu), sem esperar o próximo ciclo. */
    #[On('field-refresh')]
    public function refresh(): void
    {
        [$serve, $deliver] = $this->counts();

        if ($serve > $this->toServe || $deliver > $this->toDeliver) {
            $this->dispatch('field-alert');
        }

        [$this->toServe, $this->toDeliver] = [$serve, $deliver];
    }

    public function render()
    {
        $user = Auth::guard('web')->user();
        $tabs = [];

        if ($user->can('tables.manage')) {
            $tabs[] = ['route' => 'tables.map', 'label' => 'Mesas', 'icon' => 'table', 'match' => ['tables.map', 'tables.command'], 'badge' => 0];
            $tabs[] = ['route' => 'tables.ready', 'label' => 'Prontos', 'icon' => 'bell', 'match' => ['tables.ready'], 'badge' => $this->toServe];
        }

        if ($user->can('deliveries.operate')) {
            $tabs[] = ['route' => 'delivery.board', 'label' => 'Entregas', 'icon' => 'truck', 'match' => ['delivery.board'], 'badge' => $this->toDeliver];
        }

        $tabs[] = ['route' => 'profile', 'label' => 'Minha conta', 'icon' => 'user', 'match' => ['profile'], 'badge' => 0];

        return view('livewire.tenant.field-nav', ['tabs' => $tabs]);
    }
}
