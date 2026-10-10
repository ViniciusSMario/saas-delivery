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
    /** Aba no celular: ready (para pegar) | mine (comigo). No computador aparecem as duas. */
    public string $tab = 'ready';

    public ?string $notice = null;

    public function mount(): void
    {
        $this->tab = $this->mineQuery()->exists() ? 'mine' : 'ready';
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'mine' ? 'mine' : 'ready';
        $this->notice = null;
    }

    public function take(int $orderId, Deliveries $deliveries): void
    {
        $this->authorize('deliveries.operate');

        $order = $deliveries->take(Order::findOrFail($orderId), $this->user());
        $this->notice = "Pedido #{$order->number} está com você. Pegue mais ou vá em “Comigo” para sair.";
        $this->dispatch('field-refresh');
    }

    public function assign(int $orderId, int $courierId, Deliveries $deliveries): void
    {
        $this->authorize('orders.manage');

        $deliveries->assign(Order::with('courier')->findOrFail($orderId), User::findOrFail($courierId), $this->user());
    }

    public function complete(int $orderId, Deliveries $deliveries): void
    {
        $this->authorize('deliveries.operate');

        $order = $deliveries->complete(Order::findOrFail($orderId), $this->user());
        $this->notice = "Pedido #{$order->number} entregue.";
        $this->dispatch('field-refresh');

        if ($this->tab === 'mine' && ! $this->mineQuery()->exists()) {
            $this->tab = 'ready';
        }
    }

    private function user(): User
    {
        return Auth::guard('web')->user();
    }

    private function base()
    {
        return Order::query()->where('fulfillment', Fulfillment::Delivery->value)->with(['courier', 'payments.method', 'items']);
    }

    private function mineQuery()
    {
        return $this->base()->where('status', OrderStatus::Dispatched->value)->where('courier_id', $this->user()->id);
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

        $mine = $this->mineQuery()->orderBy('dispatched_at')->get();
        $city = $establishment->city;
        $route = null;

        if ($mine->isNotEmpty()) {
            $stops = $mine->map(fn (Order $o) => self::mapAddress($o, $city));
            $route = 'https://www.google.com/maps/dir/?api=1&travelmode=driving'
                .($establishment->address() ? '&origin='.urlencode($establishment->address()) : '')
                .'&destination='.urlencode($stops->last())
                .($stops->count() > 1 ? '&waypoints='.urlencode($stops->slice(0, -1)->implode('|')) : '');
        }

        $completedToday = $this->base()->where('status', OrderStatus::Completed->value)->whereNotNull('courier_id')->where('completed_at', '>=', now()->startOfDay())
            ->when(! $manager, fn ($q) => $q->where('courier_id', $user->id))
            ->get();
        $mineToday = $completedToday->where('courier_id', $user->id);

        return view('livewire.tenant.delivery.board', [
            'manager' => $manager,
            'city' => $city,
            'ready' => $this->base()->where('status', OrderStatus::Ready->value)->oldest()->get(),
            'preparing' => $this->base()->whereIn('status', [OrderStatus::Received->value, OrderStatus::Preparing->value])->oldest()->get(),
            'mine' => $mine,
            'route' => $route,
            'others' => $manager ? $this->base()->where('status', OrderStatus::Dispatched->value)->where(fn ($q) => $q->whereNull('courier_id')->orWhere('courier_id', '!=', $user->id))->orderBy('dispatched_at')->get() : collect(),
            'couriers' => $manager ? $this->couriers() : collect(),
            'myDay' => ['count' => $mineToday->count(), 'fees' => Money::cents($mineToday->sum(fn (Order $o) => $o->delivery_fee_cents->cents))],
            'summary' => $manager ? $completedToday->groupBy('courier_id')->map(fn (Collection $orders) => [
                'courier' => $orders->first()->courier?->name ?? '—',
                'count' => $orders->count(),
                'fees' => Money::cents($orders->sum(fn (Order $o) => $o->delivery_fee_cents->cents)),
            ])->values() : collect(),
        ]);
    }
}
