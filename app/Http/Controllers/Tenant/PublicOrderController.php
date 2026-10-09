<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Establishment\Models\Establishment;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Establishment\OpeningSchedule;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PaymentMethod;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pedido online pelo cardápio público. Funciona nos dois formatos de URL
 * (subdomínio e /cardapio/{slug}): os links partem da raiz do cardápio da requisição.
 */
class PublicOrderController
{
    public const MAX_ORDERS_PER_WINDOW = 10;

    public function checkout(Request $request): View
    {
        $timezone = Setting::read('timezone', 'America/Sao_Paulo');

        return view('public-menu.checkout', [
            'establishment' => Establishment::current(),
            'base' => $this->base($request, '/pedido'),
            'isOpen' => OpeningSchedule::load()->isOpenAt(CarbonImmutable::now($timezone)),
            'acceptsOnline' => (bool) Setting::read('accepts_online_orders', true),
            'acceptsDelivery' => (bool) Setting::read('accepts_delivery', true),
            'acceptsPickup' => (bool) Setting::read('accepts_pickup', true),
            'deliveryFee' => Money::cents((int) Setting::read('delivery_fee_cents', 0)),
            'minOrder' => Money::cents((int) Setting::read('min_order_cents', 0)),
            'paymentMethods' => PaymentMethod::active()->get(),
        ]);
    }

    public function store(Request $request, PlaceOrder $placeOrder): RedirectResponse
    {
        $base = $this->base($request, '/pedido');
        $key = 'online-order:'.tenant()->getKey().'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ORDERS_PER_WINDOW)) {
            return back()->withInput()->withErrors(['lines' => 'Muitos pedidos em pouco tempo. Aguarde alguns minutos.']);
        }

        $cart = json_decode((string) $request->input('cart'), true);
        $lines = collect(is_array($cart) ? $cart : [])
            ->map(fn ($line) => [
                'type' => ($line['type'] ?? '') === 'combo' ? 'combo' : 'product',
                'id' => (int) ($line['id'] ?? 0),
                'quantity' => (int) ($line['quantity'] ?? 0),
                'addons' => array_map('intval', (array) ($line['addons'] ?? [])),
                'notes' => is_string($line['notes'] ?? null) ? $line['notes'] : null,
            ])
            ->values()
            ->all();

        $changeFor = $request->filled('change_for') && Money::isValid($request->input('change_for'))
            ? Money::parse($request->input('change_for'))
            : null;

        try {
            $order = $placeOrder->handle([
                'lines' => $lines,
                'fulfillment' => (string) $request->input('fulfillment'),
                'customer' => ['name' => $request->input('name'), 'phone' => $request->input('phone')],
                'address' => (array) $request->input('address', []),
                'payment_method' => (string) $request->input('payment_method'),
                'change_for' => $changeFor,
                'notes' => $request->input('notes'),
            ], OrderSource::Online);
        } catch (ValidationException $e) {
            return redirect($base)->withInput($request->except('cart'))->withErrors($e->errors());
        }

        RateLimiter::hit($key, 600);

        return redirect($base.'/'.$order->public_token.'?novo=1');
    }

    public function show(Request $request, string $token): View
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{32}$/', $token), 404);

        // Busca no schema do tenant atual: token de outro estabelecimento não existe aqui.
        $order = Order::where('public_token', $token)->with(['items.addons', 'payments.method', 'history'])->firstOrFail();

        return view('public-menu.order', [
            'order' => $order,
            'establishment' => Establishment::current(),
            'menuUrl' => Str::beforeLast($this->base($request, '/pedido/'.$token), '/pedido'),
            'timezone' => Setting::read('timezone', 'America/Sao_Paulo'),
        ]);
    }

    /** Situação resumida para a aba "Pedidos" do cardápio e a atualização automática. */
    public function status(string $token): JsonResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{32}$/', $token), 404);

        $order = Order::where('public_token', $token)->withCount('items')->firstOrFail();
        $timezone = Setting::read('timezone', 'America/Sao_Paulo');

        return response()->json([
            'number' => $order->number,
            'status' => $order->status->value,
            'label' => $order->status->customerLabel($order->fulfillment),
            'final' => $order->status->isFinal(),
            'total' => $order->total_cents->format(),
            'items' => $order->items_count,
            'placed_at' => $order->created_at->timezone($timezone)->format('d/m \à\s H:i'),
        ]);
    }

    /** URL do trecho atual sem o sufixo da rota, ex.: ".../cardapio/pedido". */
    private function base(Request $request, string $suffix): string
    {
        $url = rtrim($request->url(), '/');

        return Str::endsWith($url, $suffix) ? Str::beforeLast($url, $suffix).'/pedido' : $url;
    }
}
