<?php

namespace App\Modules\Messaging\Providers;

interface WhatsAppProvider
{
    /**
     * Envia a mensagem para um celular brasileiro (DDD + número, sem o 55).
     *
     * @return string|null id da mensagem no provedor
     */
    public function send(string $phone, string $text): ?string;
}
