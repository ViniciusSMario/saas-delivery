<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Establishment\Models\Establishment;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Establishment\OpeningSchedule;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Combo;
use App\Modules\Menu\Models\Product;
use App\Modules\Menu\Models\ProductImage;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
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

        return view('public-menu.index', [
            'establishment' => Establishment::current(),
            'welcome' => Setting::read('welcome_message'),
            'isOpen' => $schedule->isOpenAt($now),
            'todayLabel' => $schedule->todayLabel($now),
            'week' => $schedule->week(),
            'categories' => $categories,
            'featured' => $categories->flatMap->products->where('is_featured', true)->values(),
            'combos' => $combos,
            'deliveryFee' => Money::cents((int) Setting::read('delivery_fee_cents', 0)),
            'minOrder' => Money::cents((int) Setting::read('min_order_cents', 0)),
            'acceptsDelivery' => (bool) Setting::read('accepts_delivery', true),
            'acceptsPickup' => (bool) Setting::read('accepts_pickup', true),
            // Raiz do cardápio nesta URL (com ou sem o slug no caminho).
            'base' => rtrim($request->url(), '/'),
        ]);
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
}
