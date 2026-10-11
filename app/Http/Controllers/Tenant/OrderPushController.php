<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Establishment\Models\Establishment;
use App\Modules\Orders\Models\Order;
use App\Modules\Push\Models\OrderPushSubscription;
use App\Modules\Push\PushEndpoint;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Cliente na página do pedido: "Avisar no meu celular". Sem conta: quem tem o link do
 * pedido (token) pode ligar os avisos daquele pedido, e eles somem quando o pedido termina.
 */
class OrderPushController
{
    public function store(Request $request, string $token): Response
    {
        $order = $this->order($token);
        abort_if($order->status->isFinal(), 422, 'Este pedido já terminou.');

        $data = $request->validate(PushEndpoint::RULES);
        abort_unless(PushEndpoint::isAllowed($data['endpoint']), 422, 'Serviço de notificações não reconhecido.');

        $existing = OrderPushSubscription::where('order_id', $order->id)->where('endpoint', $data['endpoint'])->exists();
        abort_if(! $existing && OrderPushSubscription::where('order_id', $order->id)->count() >= OrderPushSubscription::MAX_PER_ORDER, 422, 'Limite de aparelhos para este pedido.');

        // Endereço da página do pedido como o cliente abriu (subdomínio ou /cardapio/{slug}), sem o host.
        $page = '/'.Str::beforeLast($request->path(), '/avisos');

        OrderPushSubscription::updateOrCreate(['order_id' => $order->id, 'endpoint' => $data['endpoint']], [
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            'page_url' => $page,
            'icon_url' => Establishment::current()->logo_path ? Str::beforeLast($page, '/pedido/').'/logo' : null,
        ]);

        return response()->noContent();
    }

    public function destroy(Request $request, string $token): Response
    {
        OrderPushSubscription::where('order_id', $this->order($token)->id)
            ->where('endpoint', (string) $request->input('endpoint'))
            ->delete();

        return response()->noContent();
    }

    private function order(string $token): Order
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{32}$/', $token), 404);

        return Order::where('public_token', $token)->firstOrFail();
    }
}
