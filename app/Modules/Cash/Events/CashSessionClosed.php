<?php

namespace App\Modules\Cash\Events;

use App\Modules\Cash\Models\CashSession;
use Illuminate\Foundation\Events\Dispatchable;

/** Caixa fechado (com conferência). O financeiro escuta para lançar os recebimentos. */
class CashSessionClosed
{
    use Dispatchable;

    public function __construct(public readonly CashSession $session)
    {
    }
}
