<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Delivery\DeliveryFees;
use App\Modules\Establishment\Models\Establishment;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Establishment\OpeningSchedule;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Combo;
use App\Modules\Menu\Models\Product;
use App\Modules\Menu\Models\ProductImage;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\PaymentMethod;
use App\Modules\Orders\OrderEta;
use App\Modules\Orders\RushMode;
use App\Modules\Promotions\CouponRules;
use App\Modules\Promotions\Loyalty;
use App\Modules\Tables\Models\Command;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tables\TableOrdering;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cardápio público (vitrine). Responde em empresa1.meusistema.com/cardapio e em
 * meusistema.com/cardapio/{slug}; o tenant já foi resolvido pelo middleware.
 */
class PublicMenuController
{
    public function index(Request $request): View
    {
        return $this->render($request);
    }

    /** Cardápio aberto pelo QR code da mesa: os pedidos vão para a comanda (TableOrdering). */
    public function table(Request $request, string $token, TableOrdering $ordering): View
    {
        $table = $ordering->find($token) ?? abort(404);

        return $this->render($request, $table, $ordering);
    }

    private function render(Request $request, ?DiningTable $table = null, ?TableOrdering $ordering = null): View
    {
        $timezone = Setting::read('timezone', 'America/Sao_Paulo');
        $now = CarbonImmutable::now($timezone);
        $schedule = OpeningSchedule::load();
        $base = $this->base($request, $table ? '/mesa/'.$table->qr_token : '');
        $tableState = $table ? $ordering->state($table) : null;

        $categories = Category::active()->ordered()
            ->with(['products' => fn ($q) => $q->active()
                ->with([
                    'images',
                    'addonGroups' => fn ($q) => $q->where('is_active', true),
                    'addonGroups.addons' => fn ($q) => $q->where('is_active', true),
                ])])
            ->get()
            ->filter(fn (Category $category) => $category->products->isNotEmpty())
            ->values();

        $combos = Combo::where('is_active', true)->orderBy('position')->with('items.product')->get()
            ->filter(fn (Combo $combo) => $combo->isAvailable())
            ->values();

        $isOpen = $schedule->isOpenAt($now);
        $pausedUntil = RushMode::pausedUntil();
        $acceptsDelivery = (bool) Setting::read('accepts_delivery', true);
        $acceptsPickup = (bool) Setting::read('accepts_pickup', true);
        $products = $categories->flatMap->products;
        $popular = $this->popularProductIds();
        $featured = $products->where('is_featured', true)->values();

        return view('public-menu.index', [
            'establishment' => Establishment::current(),
            'welcome' => Setting::read('welcome_message'),
            'isOpen' => $isOpen,
            // Pedido online liberado: aberto agora, recebendo pedidos (sem pausa) e com entrega ou retirada.
            // Na mesa: conforme o pedido pelo QR code (modo e comanda aberta).
            'ordering' => $table
                ? $tableState['ordering']
                : $isOpen && Setting::read('accepts_online_orders', true) && ! $pausedUntil && ($acceptsDelivery || $acceptsPickup),
            'table' => $table,
            'tableState' => $tableState,
            'tableUrl' => $table ? $base.'/mesa/'.$table->qr_token : null,
            'tableBill' => ($tableState['command'] ?? null) ? $this->bill($tableState['command']) : null,
            'tableSent' => $table && $request->filled('enviado') ? (int) $request->query('enviado') : null,
            'loyaltyHeadline' => ! $table && app(Loyalty::class)->enabled() ? app(Loyalty::class)->headline() : null,
            'pausedUntil' => $isOpen && Setting::read('accepts_online_orders', true) ? $pausedUntil : null,
            'rushMinutes' => RushMode::extraMinutes(),
            'catalog' => $this->catalog($categories, $base),
            'todayLabel' => $schedule->todayLabel($now),
            'week' => $schedule->week(),
            'today' => $now->dayOfWeek,
            'categories' => $categories,
            // Sem destaques escolhidos pelo restaurante, a vitrine mostra os mais pedidos.
            'featured' => $featured->isNotEmpty() ? $featured : $products->whereIn('id', $popular)->sortBy(fn ($p) => array_search($p->id, $popular))->values(),
            'featuredTitle' => $featured->isNotEmpty() ? 'Destaques' : 'Mais pedidos',
            'popular' => $popular,
            'etaDelivery' => OrderEta::now(Fulfillment::Delivery),
            'etaPickup' => OrderEta::now(Fulfillment::Pickup),
            'coupons' => app(CouponRules::class)->publicCoupons(),
            'timezone' => $timezone,
            'combos' => $combos,
            'deliveryFee' => app(DeliveryFees::class)->cheapest(),
            'zones' => DeliveryFees::byZone() ? app(DeliveryFees::class)->zones() : collect(),
            'minOrder' => Money::cents((int) Setting::read('min_order_cents', 0)),
            'acceptsDelivery' => $acceptsDelivery,
            'acceptsPickup' => $acceptsPickup,
            'paymentMethods' => PaymentMethod::active()->get(),
            'base' => $base,
        ]);
    }

    /**
     * Conta da mesa para o cliente: pedidos (com itens e situação), taxa de serviço e total.
     *
     * @return array{orders: Collection, subtotal: Money, service: Money, total: Money}
     */
    private function bill(Command $command): array
    {
        $orders = $command->orders()->where('status', '!=', OrderStatus::Cancelled->value)->with('items.addons')->get();
        $subtotal = Money::cents((int) $orders->sum(fn ($order) => $order->total_cents->cents));
        $service = Command::serviceFeeFor($subtotal, $command->service_fee_enabled);

        return ['orders' => $orders, 'subtotal' => $subtotal, 'service' => $service, 'total' => $subtotal->add($service)];
    }

    /** Manifesto para "Adicionar à tela inicial": o cardápio abre como um app. */
    public function manifest(Request $request): JsonResponse
    {
        $establishment = Establishment::current();
        $base = $this->base($request, '/manifest.webmanifest');
        $name = $establishment->trade_name ?: tenant()->name;

        return response()->json([
            'name' => $name,
            'short_name' => mb_strimwidth($name, 0, 24, '…'),
            'start_url' => $base,
            'scope' => $base.'/',
            'display' => 'standalone',
            'background_color' => '#f5f5f4',
            'theme_color' => '#d4461b',
            'icons' => $establishment->logo_path
                ? [['src' => $base.'/logo', 'sizes' => '512x512', 'purpose' => 'any']]
                : [],
        ], headers: ['Content-Type' => 'application/manifest+json']);
    }

    /**
     * Os 3 produtos mais vendidos nos últimos 30 dias (mínimo de 3 unidades), para o selo
     * "Mais pedido". Guardado por 10 minutos: o cardápio é a página mais acessada.
     *
     * @return list<int>
     */
    private function popularProductIds(): array
    {
        return Cache::remember('menu-popular:'.tenant()->getKey(), 600, fn () => DB::connection('tenant')->table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->where('orders.created_at', '>=', now()->subDays(30))
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->havingRaw('sum(order_items.quantity) >= 3')
            ->orderByRaw('sum(order_items.quantity) desc')
            ->limit(3)
            ->pluck('order_items.product_id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    /** Dados que o app do cardápio (JS) usa: detalhe do produto, adicionais e busca. */
    private function catalog(Collection $categories, string $base): array
    {
        return $categories->flatMap(fn (Category $category) => $category->products->map(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'category' => $category->name,
            'price' => $product->price_cents->cents,
            'available' => (bool) $product->is_available,
            'images' => $product->images->map(fn (ProductImage $image) => $base.'/imagens/'.$image->id)->values(),
            'groups' => $product->addonGroups
                ->filter(fn ($group) => $group->addons->isNotEmpty())
                ->map(fn ($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'rule' => $group->rule(),
                    'min' => $group->min_choices,
                    'max' => $group->max_choices,
                    'addons' => $group->addons->map(fn ($addon) => [
                        'id' => $addon->id,
                        'name' => $addon->name,
                        'price' => $addon->price_cents->cents,
                    ])->values(),
                ])->values(),
        ]))->keyBy('id')->all();
    }

    public function productImage(ProductImage $image): Response
    {
        // Binding na conexão do tenant: id de outro estabelecimento não existe aqui.
        abort_unless($image->product()->active()->exists(), 404);

        return $this->file($image->path);
    }

    public function comboImage(Combo $combo): Response
    {
        abort_unless($combo->is_active && $combo->image_path, 404);

        return $this->file($combo->image_path);
    }

    private function file(string $path): Response
    {
        abort_unless(Storage::disk('tenant')->exists($path), 404);

        return Storage::disk('tenant')->response($path, headers: ['Cache-Control' => 'public, max-age=3600']);
    }

    /** Raiz do cardápio nesta URL (com ou sem o slug no caminho). */
    private function base(Request $request, string $suffix = ''): string
    {
        $url = rtrim($request->url(), '/');

        return $suffix !== '' && str_ends_with($url, $suffix) ? substr($url, 0, -strlen($suffix)) : $url;
    }
}
