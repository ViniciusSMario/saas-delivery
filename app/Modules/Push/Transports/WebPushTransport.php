<?php

namespace App\Modules\Push\Transports;

use App\Modules\Push\PushTransport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

/** Envio de verdade, criptografado e assinado com as chaves VAPID da plataforma. */
class WebPushTransport implements PushTransport
{
    public function send(Collection $subscriptions, array $payload): array
    {
        $vapid = config('push.vapid');

        if (! $vapid['public_key'] || ! $vapid['private_key']) {
            throw new RuntimeException('Notificações sem chaves VAPID: rode `php artisan push:vapid` e coloque as chaves no .env.');
        }

        $webPush = new WebPush(
            ['VAPID' => ['subject' => $vapid['subject'], 'publicKey' => $vapid['public_key'], 'privateKey' => $vapid['private_key']]],
            ['TTL' => config('push.ttl'), 'urgency' => 'high', 'topic' => substr(preg_replace('/[^A-Za-z0-9_-]/', '', $payload['tag']), 0, 32)],
            timeout: 10,
        );
        $webPush->setReuseVAPIDHeaders(true);

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(new Subscription(
                $subscription->endpoint,
                $subscription->public_key,
                $subscription->auth_token,
                $subscription->content_encoding,
            ), $json);
        }

        $expired = [];

        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                $expired[] = $report->getEndpoint();
            } elseif (! $report->isSuccess()) {
                Log::warning('Notificação não entregue ao serviço de push.', ['host' => parse_url($report->getEndpoint(), PHP_URL_HOST), 'motivo' => $report->getReason()]);
            }
        }

        return $expired;
    }
}
