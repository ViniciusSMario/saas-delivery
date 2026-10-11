<?php

namespace App\Modules\Promotions;

use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Promotions\Listeners\AwardLoyalty;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PromotionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Antes dos avisos ao cliente (PushServiceProvider): o "entregue" já fala do prêmio.
        Event::listen(OrderStatusChanged::class, AwardLoyalty::class);
    }
}
