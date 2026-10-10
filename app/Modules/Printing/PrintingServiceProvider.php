<?php

namespace App\Modules\Printing;

use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Printing\Listeners\PrintNewOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PrintingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OrderPlaced::class, PrintNewOrder::class);
    }
}
