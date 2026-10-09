<?php

namespace App\Modules\Finance\Listeners;

use App\Modules\Cash\Events\CashSessionClosed;
use App\Modules\Finance\FinanceBook;

class RecordCashSessionInFinance
{
    public function __construct(private readonly FinanceBook $book)
    {
    }

    public function handle(CashSessionClosed $event): void
    {
        $this->book->recordCashSession($event->session);
    }
}
