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
use App\Modules\Orders\Models\PaymentMethod;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $timezone = Setting::read('timezone', 'America/Sao_Paulo');
        $now = CarbonImmutable::now($timezone);
        $schedule = OpeningSchedule::load();
        $base = $this->base($request);

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
        $acceptsDelivery = (bool) Setting::read('accepts_delivery', true);
        $acceptsPickup = (bool) Setting::read('accepts_pickup', true);

        return view('public-menu.index', [
            'establishment' => Establishment::current(),
            'welcome' => Setting::read('welcome_message'),
            'isOpen' => $isOpen,
            // Pedido online liberado: aberto agora, recebendo pedidos e com entrega ou retirada.
            'ordering' => $isOpen && Setting::read('accepts_online_orders', true) && ($acceptsDelivery || $acceptsPickup),
            'catalog' => $this->catalog($categories, $base),
            'todayLabel' => $schedule->todayLabel($now),
            'week' => $schedule->week(),
            'today' => $now->dayOfWeek,
            'categories' => $categories,
            'featured' => $categories->flatMap->products->where('is_featured', true)->values(),
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
