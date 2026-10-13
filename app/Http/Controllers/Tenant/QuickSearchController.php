<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Customers\Models\Customer;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Stock\Models\StockItem;
use App\Support\Quantity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Busca rápida do painel (Ctrl+K): pedidos, clientes, produtos e insumos, cada grupo só
 * para quem tem a permissão. As telas do menu são filtradas no navegador
 * (partials/command-palette); aqui vêm só os registros.
 */
class QuickSearchController
{
    public const PER_GROUP = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim(mb_substr((string) $request->query('q', ''), 0, 80));
        $user = $request->user();

        if (mb_strlen($term) < 2 && ! ctype_digit(ltrim($term, '#'))) {
            return response()->json(['results' => []]);
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
        $digits = Customer::normalizePhone($term);
        $results = [];

        if ($user->can('orders.view')) {
            $tz = Setting::read('timezone', 'America/Sao_Paulo');
            $number = ltrim($term, '#');

            Order::query()
                ->where(fn ($q) => $q
                    ->when(ctype_digit($number) && strlen($number) <= 9, fn ($q) => $q->orWhere('number', (int) $number))
                    ->when(! ctype_digit($number), fn ($q) => $q->orWhere('customer_name', 'ilike', $like))
                    ->when(strlen($digits) >= 4, fn ($q) => $q->orWhere('customer_phone', 'like', '%'.$digits.'%')))
                ->latest('id')
                ->limit(self::PER_GROUP)
                ->get()
                ->each(function (Order $order) use (&$results, $tz) {
                    $results[] = [
                        'group' => 'Pedidos',
                        'icon' => 'orders',
                        'title' => "#{$order->number}".($order->customer_name ? " · {$order->customer_name}" : ''),
                        'subtitle' => implode(' · ', [$order->status->label(), $order->total_cents->format(), $order->created_at->timezone($tz)->format('d/m H:i')]),
                        'url' => route('orders.show', $order),
                    ];
                });
        }

        if ($user->can('customers.manage')) {
            Customer::query()
                ->where(fn ($q) => $q
                    ->where('name', 'ilike', $like)
                    ->when(strlen($digits) >= 4, fn ($q) => $q->orWhere('phone', 'like', '%'.$digits.'%')))
                ->orderByRaw('last_order_at desc nulls last')
                ->limit(self::PER_GROUP)
                ->get()
                ->each(function (Customer $customer) use (&$results) {
                    $results[] = [
                        'group' => 'Clientes',
                        'icon' => 'user',
                        'title' => $customer->name,
                        'subtitle' => Customer::formatPhone($customer->phone),
                        'url' => route('customers.show', $customer),
                    ];
                });
        }

        if ($user->can('menu.manage')) {
            Product::query()
                ->with('category')
                ->search($term)
                ->orderBy('name')
                ->limit(self::PER_GROUP)
                ->get()
                ->each(function (Product $product) use (&$results) {
                    $results[] = [
                        'group' => 'Produtos',
                        'icon' => 'book',
                        'title' => $product->name,
                        'subtitle' => implode(' · ', array_filter([
                            $product->category?->name,
                            $product->price_cents->format(),
                            ! $product->is_active ? 'inativo' : (! $product->is_available ? 'esgotado' : null),
                        ])),
                        'url' => route('menu.products.edit', $product),
                    ];
                });
        }

        if ($user->can('stock.manage') && tenant()->hasFeature('stock')) {
            StockItem::query()
                ->with('unit')
                ->where('name', 'ilike', $like)
                ->orderBy('name')
                ->limit(self::PER_GROUP)
                ->get()
                ->each(function (StockItem $item) use (&$results) {
                    $results[] = [
                        'group' => 'Insumos',
                        'icon' => 'box',
                        'title' => $item->name,
                        'subtitle' => 'Saldo: '.Quantity::format($item->quantity).' '.$item->unit->code,
                        'url' => route('stock.show', $item),
                    ];
                });
        }

        return response()->json(['results' => $results]);
    }
}
