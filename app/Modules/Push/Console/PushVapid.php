<?php

namespace App\Modules\Push\Console;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/** Gera o par de chaves VAPID da plataforma (uma vez só: trocar desinscreve todos os celulares). */
class PushVapid extends Command
{
    protected $signature = 'push:vapid';

    protected $description = 'Gera as chaves VAPID das notificações no celular (coloque no .env)';

    public function handle(): int
    {
        if (config('push.vapid.public_key')) {
            $this->warn('Já existem chaves no .env. Trocar faz todos os celulares pararem de receber até ativarem de novo.');
        }

        $keys = VAPID::createVapidKeys();

        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->info('Copie as duas linhas para o .env. A chave privada é segredo: não versione.');

        return self::SUCCESS;
    }
}
