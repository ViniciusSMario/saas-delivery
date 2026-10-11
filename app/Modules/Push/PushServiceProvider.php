<?php

namespace App\Modules\Push;

use App\Modules\Delivery\Events\CourierAssigned;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderReviewed;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Push\Console\PushVapid;
use App\Modules\Push\Listeners\AlertCustomer;
use App\Modules\Push\Listeners\AlertStaff;
use App\Modules\Push\Transports\FakePushTransport;
use App\Modules\Push\Transports\WebPushTransport;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PushServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PushTransport::class, fn () => config('push.driver') === 'fake' ? new FakePushTransport : new WebPushTransport);
    }

    public function boot(): void
    {
        Event::listen(OrderPlaced::class, AlertStaff::class);
        Event::listen(OrderStatusChanged::class, AlertStaff::class);
        Event::listen(CourierAssigned::class, AlertStaff::class);
        Event::listen(OrderReviewed::class, AlertStaff::class);
        Event::listen(OrderStatusChanged::class, AlertCustomer::class);

        if ($this->app->runningInConsole()) {
            $this->commands([PushVapid::class]);
        }
    }
}
