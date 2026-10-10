<?php

namespace App\Livewire\Tenant\Delivery;

use App\Modules\Access\Models\User;
use App\Modules\Access\Permission;
use App\Modules\Delivery\Actions\Deliveries;
use App\Modules\Establishment\Models\Establishment;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Tela das entregas (feita para o celular do entregador): pedidos prontos para
 * sair, as entregas em andamento com mapa e rota, e — para a gerência — quem
 * está com o quê e o fechamento do dia por entregador.
 */
#[Title('Entregas')]
class Board extends Component
{
    public function take(int $orderId, Deliveries $deliveries): void
    {
        $this->authorize('deliveries.operate');

        $deliveries->take(Order::findOrFail($orderId), $this->user());
    }

    public function assign(int $orderId, int $courierId, Deliveries $deliveries): void
    {
        $this->authorize('orders.manage');

        $deliveries->assign(Order::with('courier')->findOrFail($orderId), User::findOrFail($courierId), $this->user());
    }

    public function complete(int $orderId, Deliveries $deliveries): void
    {
        $this->authorize('deliveries.operate');

        $deliveries->complete(Order::findOrFail($orderId), $this->user());
    }

    private function user(): User
    {
        return Auth::guard('web')->user();
    }

    /** Endereço para o Google Maps. */
    public static function mapAddress(Order $order, ?string $city): string
    {
        return collect([trim($order->address_street.', '.$order->address_number, ', '), $order->address_district, $order->address_city ?: $city])
            ->filter()->implode(', ');
    }

    /** @return Collection<int, User> */
    private function couriers(): Collection
    {
        return User::with('roles')->where('is_active', true)->orderBy('name')->get()
            ->filter(fn (User $user) => $user->hasPermission(Permission::DeliveriesOperate))
            ->values();
    }

    public function render()
    {
        $user = $this->user();
        $manager = $user->can('orders.manage');
        $establishment = Establishment::current();
        $base = Order::query()->where('fulfillment', Fulfillment::Delivery->value)->with(['courier', 'payments.method', 'items']);

        $mine = (clone $base)->where('status', OrderStatus::Dispatched->value)->where('courier_id', $user->id)->orderBy('dispatched_at')->get();
        $city = $establishment->city;
        $route = null;

        if ($mine->isNotEmpty()) {
            $stops = $mine->map(fn (Order $o) => self::mapAddress($o, $city));
            $route = 'https://www.google.com/maps/dir/?api=1&travelmode=driving'
                .($establishment->address() ? '&origin='.urlencode($establishment->address()) : '')
                .'&destination='.urlencode($stops->last())
                .($stops->count() > 1 ? '&waypoints='.urlencode($stops->slice(0, -1)->implode('|')) : '');
        }

        $completedToday = $manager
            ? (clone $base)->where('status', OrderStatus::Completed->value)->whereNotNull('courier_id')->where('completed_at', '>=', now()->startOfDay())->get()
            : collect();

        return view('livewire.tenant.delivery.board', [
            'manager' => $manager,
            'city' => $city,
            'ready' => (clone $base)->where('status', OrderStatus::Ready->value)->whereNull('courier_id')->oldest()->get(),
            'preparing' => (clone $base)->whereIn('status', [OrderStatus::Received->value, OrderStatus::Preparing->value])->oldest()->get(),
            'mine' => $mine,
            'route' => $route,
            'others' => $manager ? (clone $base)->where('status', OrderStatus::Dispatched->value)->where(fn ($q) => $q->whereNull('courier_id')->orWhere('courier_id', '!=', $user->id))->orderBy('dispatched_at')->get() : collect(),
            'couriers' => $manager ? $this->couriers() : collect(),
            'summary' => $completedToday->groupBy('courier_id')->map(fn (Collection $orders) => [
                'courier' => $orders->first()->courier?->name ?? '—',
                'count' => $orders->count(),
                'fees' => Money::cents($orders->sum(fn (Order $o) => $o->delivery_fee_cents->cents)),
            ])->values(),
        ]);
    }
}
