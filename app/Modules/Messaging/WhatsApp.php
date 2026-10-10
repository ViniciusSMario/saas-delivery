<?php

namespace App\Modules\Messaging;

use App\Modules\Messaging\Jobs\SendWhatsAppMessage;
use App\Modules\Messaging\Models\WhatsAppMessage;
use App\Modules\Messaging\Providers\CloudProvider;
use App\Modules\Messaging\Providers\EvolutionProvider;
use App\Modules\Messaging\Providers\WhatsAppProvider;
use App\Modules\Orders\Models\Order;
use RuntimeException;

/** Avisos automáticos ao cliente pelo WhatsApp. */
class WhatsApp
{
    /** Põe na fila o aviso deste momento do pedido, se estiver ligado (e ainda não foi enviado). */
    public function notify(Order $order, string $event): ?WhatsAppMessage
    {
        if (! WhatsAppSettings::automatic() || ! WhatsAppSettings::eventEnabled($event) || ! OrderMessages::notifiable($order)) {
            return null;
        }

        $already = WhatsAppMessage::where('order_id', $order->id)->where('event', $event)->whereIn('status', ['queued', 'sent'])->exists();

        if ($already) {
            return null;
        }

        $message = WhatsAppMessage::create([
            'order_id' => $order->id,
            'event' => $event,
            'phone' => $order->customer_phone,
            'body' => OrderMessages::render($order, $event),
            'provider' => WhatsAppSettings::provider(),
        ]);

        SendWhatsAppMessage::dispatch($message->id);

        return $message;
    }

    /** Envio imediato (mensagem de teste da tela de configuração). */
    public function sendNow(string $phone, string $text): WhatsAppMessage
    {
        $message = WhatsAppMessage::create([
            'event' => 'test',
            'phone' => $phone,
            'body' => $text,
            'provider' => WhatsAppSettings::provider(),
        ]);

        SendWhatsAppMessage::dispatchSync($message->id);

        return $message->refresh();
    }

    public function provider(): WhatsAppProvider
    {
        $c = WhatsAppSettings::credentials();

        return match (WhatsAppSettings::provider()) {
            'evolution' => new EvolutionProvider($c['evolution_url'] ?? '', $c['evolution_instance'] ?? '', $c['evolution_key'] ?? ''),
            'cloud' => new CloudProvider($c['cloud_phone_id'] ?? '', $c['cloud_token'] ?? '', $c['cloud_template'] ?? '', $c['cloud_language'] ?? 'pt_BR'),
            default => throw new RuntimeException('Escolha um provedor de envio automático.'),
        };
    }
}
