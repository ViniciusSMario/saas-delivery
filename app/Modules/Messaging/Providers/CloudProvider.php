<?php

namespace App\Modules\Messaging\Providers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * WhatsApp Cloud API (Meta). Mensagem iniciada pela empresa precisa de um modelo
 * aprovado: configure um com uma variável no corpo (ex.: "Atualização do seu
 * pedido: {{1}}") e o texto do aviso vai nela. Sem modelo, envia texto livre
 * (só funciona até 24 h depois de o cliente escrever).
 */
class CloudProvider implements WhatsAppProvider
{
    public function __construct(
        private readonly string $phoneNumberId,
        private readonly string $token,
        private readonly string $template = '',
        private readonly string $language = 'pt_BR',
    ) {
    }

    public function send(string $phone, string $text): ?string
    {
        if ($this->phoneNumberId === '' || $this->token === '') {
            throw new RuntimeException('Preencha o ID do número e o token da API do WhatsApp.');
        }

        $message = $this->template !== ''
            ? ['type' => 'template', 'template' => [
                'name' => $this->template,
                'language' => ['code' => $this->language ?: 'pt_BR'],
                // Variável de modelo não aceita quebra de linha nem muitos espaços seguidos.
                'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => preg_replace('/\s+/', ' ', $text)]]]],
            ]]
            : ['type' => 'text', 'text' => ['body' => $text]];

        $response = Http::withToken($this->token)->acceptJson()->timeout(15)
            ->post('https://graph.facebook.com/v21.0/'.rawurlencode($this->phoneNumberId).'/messages', ['messaging_product' => 'whatsapp', 'to' => '55'.$phone] + $message);

        if ($response->failed()) {
            throw new RuntimeException('WhatsApp Cloud API: '.($response->json('error.message') ?? 'erro '.$response->status()));
        }

        return $response->json('messages.0.id');
    }
}
