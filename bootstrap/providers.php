<?php

use App\Modules\Access\AccessServiceProvider;
use App\Modules\Finance\FinanceServiceProvider;
use App\Modules\Messaging\MessagingServiceProvider;
use App\Modules\Orders\OrdersServiceProvider;
use App\Modules\Printing\PrintingServiceProvider;
use App\Modules\Stock\StockServiceProvider;
use App\Platform\PlatformServiceProvider;
use App\Providers\AppServiceProvider;
use App\Tenancy\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    // Antes do TenancyServiceProvider: a rota Livewire do admin precisa ser registrada primeiro.
    PlatformServiceProvider::class,
    TenancyServiceProvider::class,
    AccessServiceProvider::class,
    StockServiceProvider::class,
    FinanceServiceProvider::class,
    PrintingServiceProvider::class,
    MessagingServiceProvider::class,
    OrdersServiceProvider::class,
];
