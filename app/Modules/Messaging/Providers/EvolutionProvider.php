<?php

namespace App\Modules\Messaging\Providers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Evolution API (https://doc.evolution-api.com): conecta um número de WhatsApp pelo
 * QR code, sem aprovação de modelos. POST {url}/message/sendText/{instância}.
 */
class EvolutionProvider implements WhatsAppProvider
{
    public function __construct(
        private readonly string $url,
        private readonly string $instance,
        private readonly string $apiKey,
    ) {
    }

    public function send(string $phone, string $text): ?string
    {
        if ($this->url === '' || $this->instance === '' || $this->apiKey === '') {
            throw new RuntimeException('Preencha o endereço, a instância e a chave da Evolution API.');
        }

        $endpoint = rtrim($this->url, '/').'/message/sendText/'.rawurlencode($this->instance);
        SafeUrl::assert($endpoint);

        $response = Http::withHeaders(['apikey' => $this->apiKey])->acceptJson()->timeout(15)
            ->post($endpoint, ['number' => '55'.$phone, 'text' => $text]);

        if ($response->failed()) {
            throw new RuntimeException('Evolution API respondeu '.$response->status().': '.mb_substr($response->body(), 0, 200));
        }

        return $response->json('key.id');
    }
}
