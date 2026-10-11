<?php

namespace App\Modules\Orders;

use App\Modules\Menu\Models\Combo;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;

/**
 * "Pedir de novo": monta o carrinho com os itens de um pedido antigo, conferidos contra o
 * cardápio de hoje (preço atual; item fora do cardápio, esgotado ou com opção que mudou fica
 * de fora e é avisado). O pedido em si continua sendo conferido em PlaceOrder.
 */
class Reorder
{
    /**
     * @return array{lines: list<array{type: string, id: int, name: string, unit: int, addons: list<array{id: int, name: string, price: int}>, quantity: int, notes: string}>, skipped: list<string>}
     */
    public function linesFor(Order $order): array
    {
        $order->loadMissing('items.addons');

        $products = Product::active()
            ->with(['addonGroups' => fn ($q) => $q->where('is_active', true), 'addonGroups.addons' => fn ($q) => $q->where('is_active', true)])
            ->whereKey($order->items->pluck('product_id')->filter())
            ->get()
            ->keyBy('id');
        $combos = Combo::where('is_active', true)->with('items.product')->whereKey($order->items->pluck('combo_id')->filter())->get()->keyBy('id');

        $lines = [];
        $skipped = [];

        foreach ($order->items as $item) {
            $line = $item->combo_id ? $this->comboLine($item, $combos->get($item->combo_id)) : $this->productLine($item, $products->get($item->product_id));

            if ($line) {
                $lines[] = $line;
            } else {
                $skipped[] = $item->name;
            }
        }

        return ['lines' => $lines, 'skipped' => array_values(array_unique($skipped))];
    }

    private function comboLine(OrderItem $item, ?Combo $combo): ?array
    {
        if (! $combo || ! $combo->isAvailable()) {
            return null;
        }

        return [
            'type' => 'combo', 'id' => $combo->id, 'name' => $combo->name, 'unit' => $combo->price_cents->cents,
            'addons' => [], 'quantity' => (int) $item->quantity, 'notes' => '',
        ];
    }

    private function productLine(OrderItem $item, ?Product $product): ?array
    {
        if (! $product || ! $product->is_available) {
            return null;
        }

        $available = $product->addonGroups->flatMap->addons->keyBy('id');
        $chosen = $item->addons->pluck('addon_id');

        // Adicional que saiu do cardápio (ou do produto): o cliente precisa escolher de novo.
        if ($chosen->contains(fn ($id) => ! $id || ! $available->has($id))) {
            return null;
        }

        foreach ($product->addonGroups as $group) {
            $count = $chosen->filter(fn ($id) => $group->addons->contains('id', $id))->count();

            if ($count < $group->min_choices || $count > $group->max_choices) {
                return null;
            }
        }

        return [
            'type' => 'product', 'id' => $product->id, 'name' => $product->name, 'unit' => $product->price_cents->cents,
            'addons' => $chosen->map(fn ($id) => ['id' => (int) $id, 'name' => $available[$id]->name, 'price' => $available[$id]->price_cents->cents])->values()->all(),
            'quantity' => (int) $item->quantity,
            'notes' => (string) $item->notes,
        ];
    }
}
