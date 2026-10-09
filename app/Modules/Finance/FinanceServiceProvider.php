<?php

namespace App\Modules\Finance;

use App\Modules\Cash\Events\CashSessionClosed;
use App\Modules\Finance\Listeners\RecordCashSessionInFinance;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(CashSessionClosed::class, RecordCashSessionInFinance::class);
    }
}
