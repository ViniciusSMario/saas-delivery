<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Delivery\DeliveryFees;
use App\Modules\Establishment\Models\Establishment;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Establishment\OpeningSchedule;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Actions\ReviewOrder;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PaymentMethod;
use App\Modules\Orders\OrderEta;
use App\Modules\Orders\Reorder;
use App\Modules\Orders\RushMode;
use App\Modules\Customers\Models\Customer;
use App\Modules\Promotions\CouponRules;
use App\Modules\Promotions\Loyalty;
use App\Modules\Promotions\Models\Coupon;
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
            'pausedUntil' => RushMode::pausedUntil(),
            'timezone' => $timezone,
            'acceptsDelivery' => (bool) Setting::read('accepts_delivery', true),
            'acceptsPickup' => (bool) Setting::read('accepts_pickup', true),
            'deliveryFee' => app(DeliveryFees::class)->cheapest(),
            'zones' => DeliveryFees::byZone() ? app(DeliveryFees::class)->zones() : collect(),
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

        $lines = self::cartLines($request);

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
                'coupon' => is_string($request->input('coupon')) ? $request->input('coupon') : null,
                'notes' => $request->input('notes'),
            ], OrderSource::Online);
        } catch (ValidationException $e) {
            return redirect($base)->withInput($request->except('cart'))->withErrors($e->errors());
        }

        RateLimiter::hit($key, 600);

        return redirect($base.'/'.$order->public_token.'?novo=1');
    }

    /** Itens do carrinho do navegador (JSON no campo "cart"); preços e regras são conferidos no PlaceOrder. */
    public static function cartLines(Request $request): array
    {
        $cart = json_decode((string) $request->input('cart'), true);

        return collect(is_array($cart) ? $cart : [])
            ->filter(fn ($line) => is_array($line))
            ->map(fn ($line) => [
                'type' => ($line['type'] ?? '') === 'combo' ? 'combo' : 'product',
                'id' => (int) ($line['id'] ?? 0),
                'quantity' => (int) ($line['quantity'] ?? 0),
                'addons' => array_map('intval', (array) ($line['addons'] ?? [])),
                'notes' => is_string($line['notes'] ?? null) ? $line['notes'] : null,
            ])
            ->values()
            ->all();
    }

    public function show(Request $request, string $token, Loyalty $loyalty): View
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{32}$/', $token), 404);

        // Busca no schema do tenant atual: token de outro estabelecimento não existe aqui.
        $order = Order::where('public_token', $token)->with(['items.addons', 'payments.method', 'history', 'courier', 'review'])->firstOrFail();

        return view('public-menu.order', [
            'order' => $order,
            'establishment' => Establishment::current(),
            'menuUrl' => Str::beforeLast($this->base($request, '/pedido/'.$token), '/pedido'),
            'timezone' => Setting::read('timezone', 'America/Sao_Paulo'),
            'eta' => $order->status->isFinal() ? null : OrderEta::for($order),
            // Só o primeiro nome do entregador aparece para o cliente.
            'courierName' => $order->courier ? Str::before($order->courier->name, ' ') : null,
            // Pedido entregue: "volte e ganhe" (se o restaurante tiver um cupom para isso e o cliente ainda puder usar).
            'returnCoupon' => $order->status === OrderStatus::Completed ? app(CouponRules::class)->returnOffer($order->customer_phone) : null,
            // Cartão fidelidade do cliente (pedidos do cardápio e do balcão com telefone).
            'loyalty' => $order->customer_id && $order->source !== OrderSource::Table && $order->status !== OrderStatus::Cancelled
                ? $loyalty->status(Customer::find($order->customer_id))
                : null,
            'loyaltyHeadline' => $loyalty->headline(),
            'loyaltyCounts' => $order->subtotal_cents->cents >= (int) $loyalty->config()['min_cents'],
        ]);
    }

    /** Avaliação do cliente na página do pedido (quem tem o link do pedido). */
    public function review(Request $request, string $token, ReviewOrder $reviewOrder): JsonResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{32}$/', $token), 404);
        $order = Order::where('public_token', $token)->firstOrFail();

        try {
            $review = $reviewOrder->handle(
                $order,
                (int) $request->input('rating'),
                array_filter((array) $request->input('tags', []), 'is_string'),
                is_string($request->input('comment')) ? $request->input('comment') : null,
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json(['rating' => $review->rating], 201);
    }

    /** "Pedir de novo": os itens do pedido, conferidos contra o cardápio de hoje, para o carrinho. */
    public function reorder(string $token, Reorder $reorder): JsonResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{32}$/', $token), 404);

        return response()->json($reorder->linesFor(Order::where('public_token', $token)->firstOrFail()));
    }

    /**
     * Prévia do cupom no checkout: diz se o código vale e devolve a regra para o carrinho
     * calcular o desconto enquanto o cliente mexe nos itens. O valor final é conferido no pedido.
     */
    public function coupon(Request $request, CouponRules $rules): JsonResponse
    {
        $coupon = Coupon::findByCode(is_string($request->input('code')) ? $request->input('code') : null);

        if (! $coupon) {
            return response()->json(['message' => 'Cupom não encontrado. Confira o código.'], 422);
        }

        $problem = $rules->problem(
            $coupon,
            Money::cents(max(0, (int) $request->input('subtotal'))),
            is_string($request->input('phone')) && $request->input('phone') !== '' ? $request->input('phone') : null,
            Fulfillment::tryFrom((string) $request->input('fulfillment')),
        );

        if ($problem) {
            return response()->json(['message' => $problem], 422);
        }

        return response()->json([
            'code' => $coupon->code,
            'benefit' => $coupon->benefit(),
            'type' => $coupon->type->value,
            'percent' => $coupon->percent,
            'amount' => $coupon->amount_cents?->cents,
            'max' => $coupon->max_discount_cents?->cents,
            'min' => $coupon->min_order_cents?->cents ?? 0,
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
