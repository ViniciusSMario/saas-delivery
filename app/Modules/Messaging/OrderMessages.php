<?php

namespace App\Modules\Messaging;

use App\Modules\Establishment\Models\Establishment;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Tenancy\TenantUrl;

/** Qual aviso cabe em cada momento do pedido e o texto dele, já preenchido. */
class OrderMessages
{
    public const PLACEHOLDERS = [
        '{nome}' => 'primeiro nome do cliente',
        '{numero}' => 'número do pedido',
        '{estabelecimento}' => 'nome do estabelecimento',
        '{total}' => 'valor total',
        '{link}' => 'link de acompanhamento',
        '{motivo}' => 'motivo do cancelamento',
    ];

    /** Aviso correspondente à situação atual do pedido (null = nenhum). */
    public static function eventFor(Order $order): ?string
    {
        return match ($order->status) {
            OrderStatus::Received => 'received',
            OrderStatus::Preparing => 'preparing',
            OrderStatus::Ready => $order->fulfillment === Fulfillment::Pickup ? 'ready_pickup' : null,
            OrderStatus::Dispatched => 'dispatched',
            OrderStatus::Completed => $order->fulfillment === Fulfillment::Delivery ? 'completed' : null,
            OrderStatus::Cancelled => 'cancelled',
        };
    }

    /** Pedido que pode receber aviso: tem telefone e não é de mesa. */
    public static function notifiable(Order $order): bool
    {
        return (bool) $order->customer_phone && $order->fulfillment !== Fulfillment::Table;
    }

    public static function render(Order $order, string $event, ?string $template = null): string
    {
        $name = trim(explode(' ', trim((string) $order->customer_name))[0] ?? '') ?: 'cliente';

        return strtr($template ?? WhatsAppSettings::template($event), [
            '{nome}' => $name,
            '{numero}' => (string) $order->number,
            '{estabelecimento}' => Establishment::current()->trade_name ?: (tenant()?->name ?? ''),
            '{total}' => $order->total_cents->format(),
            '{link}' => tenant() ? TenantUrl::to(tenant(), 'cardapio/pedido/'.$order->public_token) : '',
            '{motivo}' => $order->cancel_reason ?: 'não informado',
        ]);
    }

    /** Link wa.me com a mensagem pronta (modo manual e botões do painel). */
    public static function link(Order $order, ?string $event = null): ?string
    {
        if (! $order->customer_phone) {
            return null;
        }

        $event ??= self::eventFor($order);
        $text = $event ? self::render($order, $event) : '';

        return 'https://wa.me/55'.$order->customer_phone.($text !== '' ? '?text='.rawurlencode($text) : '');
    }
}
