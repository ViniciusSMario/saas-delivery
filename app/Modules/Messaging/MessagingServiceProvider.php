<?php

namespace App\Modules\Messaging;

use App\Modules\Messaging\Listeners\NotifyCustomer;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class MessagingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OrderPlaced::class, NotifyCustomer::class);
        Event::listen(OrderStatusChanged::class, NotifyCustomer::class);
    }
}
