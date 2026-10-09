<?php

namespace App\Modules\Stock;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Stock\Models\Recipe;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockMovement;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Único lugar que mexe no saldo dos insumos. Cada mudança grava uma linha em
 * stock_movements e atualiza o saldo materializado na mesma transação, com a
 * linha do insumo travada (SELECT ... FOR UPDATE).
 */
class StockLedger
{
    /** Entrada por compra. O custo médio é recalculado (média ponderada). */
    public function purchase(StockItem $item, string $quantity, Money $totalCost, ?User $user, ?string $reason = null): StockMovement
    {
        $this->assertPositive($quantity);

        if ($totalCost->cents < 0) {
            throw ValidationException::withMessages(['cost' => 'Custo inválido.']);
        }

        $unitCost = (int) Quantity::round(Quantity::div((string) $totalCost->cents, $quantity, 6));

        return $this->apply($item, $quantity, MovementType::Purchase, $user, reason: $reason, unitCost: $unitCost);
    }

    public function loss(StockItem $item, string $quantity, ?User $user, string $reason): StockMovement
    {
        $this->assertPositive($quantity);
        $this->assertReason($reason);

        return $this->apply($item, Quantity::negate($quantity), MovementType::Loss, $user, reason: $reason);
    }

    /** Inventário: informa a contagem real e lança a diferença como ajuste. */
    public function count(StockItem $item, string $counted, ?User $user, ?string $reason = null): ?StockMovement
    {
        if (Quantity::compare($counted, '0') < 0) {
            throw ValidationException::withMessages(['quantity' => 'A contagem não pode ser negativa.']);
        }

        return DB::connection('tenant')->transaction(function () use ($item, $counted, $user, $reason) {
            $locked = StockItem::lockForUpdate()->findOrFail($item->id);
            $delta = Quantity::sub($counted, $locked->quantity);

            if (Quantity::compare($delta, '0') === 0) {
                return null;
            }

            return $this->apply($locked, $delta, MovementType::Adjustment, $user, reason: $reason ?: 'Contagem de inventário');
        });
    }

    /**
     * Baixa os insumos de um pedido pela ficha técnica (produtos, adicionais e combos).
     * Idempotente: um pedido só baixa uma vez. Estoque negativo é permitido.
     */
    public function deductOrder(Order $order, ?User $user = null): void
    {
        DB::connection('tenant')->transaction(function () use ($order, $user) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            if ($locked->stock_deducted_at) {
                return;
            }

            $required = $this->requirements($locked);

            // Trava sempre na mesma ordem (id) para duas baixas simultâneas não se travarem.
            ksort($required);

            foreach ($required as $stockItemId => $quantity) {
                $this->apply(StockItem::findOrFail($stockItemId), Quantity::negate($quantity), MovementType::Sale, $user, orderId: $locked->id, reason: "Pedido #{$locked->number}");
            }

            $locked->forceFill(['stock_deducted_at' => now()])->save();
            $order->stock_deducted_at = $locked->stock_deducted_at;

            if ($required !== [] && Setting::read('stock_auto_sold_out', false)) {
                $this->markSoldOut(array_keys($required));
            }
        });
    }

    /** Pedido cancelado depois da baixa: os insumos já foram usados e contam como perda. */
    public function orderCancelled(Order $order, ?User $user = null): void
    {
        if (! $order->stock_deducted_at) {
            return;
        }

        StockMovement::where('order_id', $order->id)->where('type', MovementType::Sale)->update([
            'type' => MovementType::Loss,
            'reason' => "Pedido #{$order->number} cancelado após o preparo",
        ]);
    }

    /**
     * Quanto de cada insumo o pedido consome, na unidade do insumo.
     *
     * @return array<int, string> stock_item_id => quantidade
     */
    public function requirements(Order $order): array
    {
        $order->loadMissing(['items.addons', 'items.combo.items']);

        $productIds = [];
        $addonIds = [];

        foreach ($order->items as $item) {
            if ($item->product_id) {
                $productIds[] = $item->product_id;
            }

            foreach ($item->combo?->items ?? [] as $comboItem) {
                $productIds[] = $comboItem->product_id;
            }

            foreach ($item->addons as $addon) {
                if ($addon->addon_id) {
                    $addonIds[] = $addon->addon_id;
                }
            }
        }

        $recipes = Recipe::with(['items.stockItem.unit', 'items.unit'])
            ->where(fn ($q) => $q->whereIn('product_id', array_unique($productIds))->orWhereIn('addon_id', array_unique($addonIds)))
            ->get();
        $byProduct = $recipes->whereNotNull('product_id')->keyBy('product_id');
        $byAddon = $recipes->whereNotNull('addon_id')->keyBy('addon_id');

        $required = [];
        $add = function (?Recipe $recipe, int $times) use (&$required) {
            foreach ($recipe?->items ?? [] as $line) {
                $perUnit = $line->unit->convert($line->quantity, $line->stockItem->unit);
                $required[$line->stock_item_id] = Quantity::add($required[$line->stock_item_id] ?? '0', Quantity::mul($perUnit, (string) $times));
            }
        };

        foreach ($order->items as $item) {
            if ($item->product_id) {
                $add($byProduct->get($item->product_id), $item->quantity);
            }

            foreach ($item->combo?->items ?? [] as $comboItem) {
                $add($byProduct->get($comboItem->product_id), $comboItem->quantity * $item->quantity);
            }

            foreach ($item->addons as $addon) {
                $add($addon->addon_id ? $byAddon->get($addon->addon_id) : null, $item->quantity);
            }
        }

        return array_filter($required, fn (string $q) => Quantity::isPositive($q));
    }

    private function apply(StockItem $item, string $delta, MovementType $type, ?User $user, ?int $orderId = null, ?string $reason = null, ?int $unitCost = null): StockMovement
    {
        return DB::connection('tenant')->transaction(function () use ($item, $delta, $type, $user, $orderId, $reason, $unitCost) {
            $locked = StockItem::lockForUpdate()->findOrFail($item->id);
            $before = $locked->quantity;
            $after = Quantity::add($before, $delta);

            if ($type === MovementType::Purchase && $unitCost !== null) {
                // Média ponderada; com saldo zerado ou negativo, vale o custo da compra.
                $locked->average_cost_cents = Quantity::isPositive($before)
                    ? (int) Quantity::round(Quantity::div(
                        Quantity::add(Quantity::mul($before, (string) $locked->average_cost_cents, 6), Quantity::mul($delta, (string) $unitCost, 6)),
                        $after,
                        6,
                    ))
                    : $unitCost;
            }

            $locked->forceFill(['quantity' => $after])->save();
            $item->setRawAttributes($locked->getAttributes(), true);

            return StockMovement::create([
                'stock_item_id' => $locked->id,
                'type' => $type,
                'quantity' => $delta,
                'balance_after' => $after,
                'unit_cost_cents' => $unitCost,
                'order_id' => $orderId,
                'user_id' => $user?->id,
                'reason' => $reason ? mb_substr(trim($reason), 0, 255) : null,
            ]);
        });
    }

    /** Opcional: produtos cujos insumos zeraram ficam "esgotado". */
    private function markSoldOut(array $stockItemIds): void
    {
        $empty = StockItem::whereKey($stockItemIds)->where('quantity', '<=', 0)->pluck('id');

        if ($empty->isEmpty()) {
            return;
        }

        $products = Product::where('is_available', true)
            ->whereIn('id', Recipe::whereNotNull('product_id')
                ->whereHas('items', fn ($q) => $q->whereIn('stock_item_id', $empty))
                ->select('product_id'))
            ->get();

        foreach ($products as $product) {
            $product->update(['is_available' => false]);
            Audit::record('product.updated', $product, ['esgotado' => true, 'motivo' => 'insumo zerado no estoque']);
        }
    }

    private function assertPositive(string $quantity): void
    {
        if (! Quantity::isPositive($quantity)) {
            throw ValidationException::withMessages(['quantity' => 'Informe uma quantidade maior que zero.']);
        }
    }

    private function assertReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Informe o motivo.']);
        }
    }
}
