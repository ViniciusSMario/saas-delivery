<?php

namespace App\Modules\Push;

use App\Modules\Establishment\Models\Establishment;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\OrderEta;
use App\Modules\Push\Jobs\SendOrderPush;
use App\Modules\Push\Models\OrderPushSubscription;
use Illuminate\Support\Str;

/**
 * Avisos no celular do cliente que pediu para acompanhar o pedido (página do pedido no cardápio):
 * aceito, pronto para retirada, saiu para entrega, entregue e cancelado.
 */
class CustomerAlerts
{
    public function statusChanged(Order $order): void
    {
        if (! OrderPushSubscription::where('order_id', $order->id)->exists()) {
            return;
        }

        $message = $this->message($order);

        if ($message) {
            [$title, $body] = $message;
            SendOrderPush::dispatch($order->id, [
                'title' => $title,
                'body' => $body,
                'tag' => "acompanhar-{$order->id}",
            ], $order->status->isFinal());
        }
    }

    /** @return array{0: string, 1: string}|null título e texto, ou null quando a etapa não interessa ao cliente */
    private function message(Order $order): ?array
    {
        $name = Establishment::current()->trade_name ?: tenant()->name;
        $number = $order->number;
        $eta = OrderEta::for($order);
        $at = $eta?->timezone(Setting::read('timezone', 'America/Sao_Paulo'))->format('H:i');
        $pickup = $order->fulfillment === Fulfillment::Pickup;

        return match ($order->status) {
            OrderStatus::Preparing => ["Pedido #{$number} aceito", "{$name} já está preparando".($at ? ". Previsão: {$at}" : '.')],
            OrderStatus::Ready => match ($order->fulfillment) {
                Fulfillment::Pickup => ["Pedido #{$number} pronto para retirada", "Pode vir buscar em {$name}."],
                Fulfillment::Delivery => ["Pedido #{$number} pronto", 'Já vai sair para entrega.'],
                default => null,
            },
            OrderStatus::Dispatched => ["Pedido #{$number} saiu para entrega", collect([
                ($courier = $order->courier()->value('name')) ? 'Com '.Str::before($courier, ' ') : null,
                $at ? "previsão {$at}" : null,
            ])->filter()->implode(' · ') ?: 'Está a caminho.'],
            OrderStatus::Completed => $pickup
                ? ["Pedido #{$number} retirado", "Obrigado por pedir em {$name}!"]
                : ["Pedido #{$number} entregue", "Bom apetite! Obrigado por pedir em {$name}."],
            OrderStatus::Cancelled => ["Pedido #{$number} cancelado", $order->cancel_reason ? "Motivo: {$order->cancel_reason}" : "Dúvidas? Fale com {$name}."],
            default => null,
        };
    }
}
