<?php

namespace App\Platform\Console;

use App\Platform\Billing\Subscriptions;
use Illuminate\Console\Command;

/** Rotina diária da cobrança: fim de teste, inadimplência e suspensão/reativação. */
class BillingCheck extends Command
{
    protected $signature = 'billing:check';

    protected $description = 'Avisa fim de teste, marca inadimplentes e suspende quem passou da carência';

    public function handle(Subscriptions $subscriptions): int
    {
        $count = $subscriptions->check();

        $this->components->info(sprintf(
            'Lembretes: %d · testes encerrados: %d · suspensos: %d · cobranças geradas: %d',
            $count['reminders'], $count['trials_ended'], $count['suspended'], $count['charges'],
        ));

        return self::SUCCESS;
    }
}
