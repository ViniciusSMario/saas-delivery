<?php

namespace App\Modules\Push;

/**
 * Quem o servidor vai chamar ao enviar um aviso: só os serviços de push dos navegadores,
 * por HTTPS. Impede usar o cadastro de inscrição para fazer o servidor chamar endereços internos.
 */
class PushEndpoint
{
    public const HOSTS = ['fcm.googleapis.com', 'android.googleapis.com', 'updates.push.services.mozilla.com', 'push.apple.com', 'notify.windows.com'];

    /** Regras de validação da inscrição que o navegador manda (PushSubscription.toJSON()). */
    public const RULES = [
        'endpoint' => ['required', 'string', 'max:2000', 'starts_with:https://'],
        'keys.p256dh' => ['required', 'string', 'max:255'],
        'keys.auth' => ['required', 'string', 'max:255'],
        'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
    ];

    public static function isAllowed(string $endpoint): bool
    {
        $parts = parse_url($endpoint);

        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['port']) || isset($parts['user'])) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');

        foreach (self::HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
