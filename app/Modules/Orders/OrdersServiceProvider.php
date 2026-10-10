<?php

namespace App\Modules\Orders;

use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Listeners\WatchMonthlyLimit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class OrdersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OrderPlaced::class, WatchMonthlyLimit::class);
    }
}
