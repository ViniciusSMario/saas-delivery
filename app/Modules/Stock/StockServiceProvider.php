<?php

namespace App\Modules\Stock;

use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Stock\Listeners\SyncStockWithOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class StockServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OrderStatusChanged::class, SyncStockWithOrder::class);
    }
}
