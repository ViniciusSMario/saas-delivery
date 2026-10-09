<?php

namespace App\Livewire\Tenant;

use App\Modules\Access\Models\User;
use App\Modules\Establishment\Models\Establishment;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Stock\Models\StockItem;
use App\Support\Money;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Início')]
class Dashboard extends Component
{
    public function render()
    {
        $timezone = Setting::read('timezone', 'America/Sao_Paulo');

        return view('livewire.tenant.dashboard', [
            'establishment' => Establishment::current(),
            'today' => OpeningHour::where('weekday', now($timezone)->dayOfWeek)->first(),
            'activeUsers' => User::where('is_active', true)->count(),
            'ordersToday' => Order::where('created_at', '>=', now($timezone)->startOfDay()->utc())->where('status', '!=', OrderStatus::Cancelled->value)->count(),
            'salesToday' => Money::cents((int) Order::where('created_at', '>=', now($timezone)->startOfDay()->utc())->where('status', '!=', OrderStatus::Cancelled->value)->sum('total_cents')),
            'openOrders' => Order::open()->count(),
            'lowStock' => auth()->user()->can('stock.manage')
                ? StockItem::belowMinimum()->with('unit')->orderBy('name')->limit(8)->get()
                : collect(),
        ]);
    }
}
