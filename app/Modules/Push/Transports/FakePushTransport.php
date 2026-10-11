<?php

namespace App\Modules\Push\Transports;

use App\Modules\Push\PushTransport;
use Illuminate\Support\Collection;

/** Para testes: guarda o que seria enviado e simula celulares que não existem mais. */
class FakePushTransport implements PushTransport
{
    /** @var list<array{user_id: int, endpoint: string, payload: array}> */
    public static array $sent = [];

    /** @var list<string> endpoints que o "serviço de push" responde como expirados */
    public static array $expired = [];

    public function send(Collection $subscriptions, array $payload): array
    {
        foreach ($subscriptions as $subscription) {
            static::$sent[] = ['user_id' => $subscription->user_id, 'endpoint' => $subscription->endpoint, 'payload' => $payload];
        }

        return array_values(array_intersect(static::$expired, $subscriptions->pluck('endpoint')->all()));
    }

    public static function reset(): void
    {
        static::$sent = [];
        static::$expired = [];
    }
}
