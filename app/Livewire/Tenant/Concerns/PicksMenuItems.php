<?php

namespace App\Livewire\Tenant\Concerns;

use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Combo;
use App\Modules\Menu\Models\Product;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Escolha de produtos, combos e adicionais em tela de atendimento (balcão, comanda).
 * Os preços aqui são só para exibir; o servidor recalcula tudo em PlaceOrder.
 */
trait PicksMenuItems
{
    public string $search = '';

    public ?int $categoryId = null;

    /** @var list<array{key: string, type: string, id: int, name: string, unit: int, addons: list<array{id: int, name: string, price: int}>, quantity: int, notes: string}> */
    public array $cart = [];

    public ?int $pickingId = null;

    /** @var array<int, list<int>> groupId => addonIds */
    public array $picked = [];

    public int $pickQuantity = 1;

    public string $pickNotes = '';

    public function addProduct(int $id): void
    {
        $product = $this->sellable()->findOrFail($id);

        if ($product->addonGroups->contains(fn ($g) => $g->addons->isNotEmpty())) {
            $this->pickingId = $product->id;
            $this->picked = [];
            $this->pickQuantity = 1;
            $this->pickNotes = '';
            $this->resetErrorBag('picked');

            return;
        }

        $this->pushLine('product', $product->id, $product->name, $product->price_cents->cents, [], 1, '');
    }

    /** Enter na busca: adiciona o primeiro resultado (código exato tem prioridade). */
    public function addFirstResult(): void
    {
        $term = trim($this->search);

        if ($term === '') {
            return;
        }

        $product = $this->sellable()->whereRaw('lower(code) = lower(?)', [$term])->first()
            ?? $this->sellable()->search($term)->orderBy('name')->first();

        if ($product) {
            $this->addProduct($product->id);
            $this->search = '';
        }
    }

    public function addCombo(int $id): void
    {
        $combo = Combo::where('is_active', true)->findOrFail($id);
        $this->pushLine('combo', $combo->id, $combo->name, $combo->price_cents->cents, [], 1, '');
    }

    public function togglePick(int $groupId, int $addonId): void
    {
        $group = $this->picking()?->addonGroups->firstWhere('id', $groupId);

        if (! $group) {
            return;
        }

        $current = $this->picked[$groupId] ?? [];

        if ($group->max_choices === 1) {
            $current = in_array($addonId, $current, true) && $group->min_choices === 0 ? [] : [$addonId];
        } elseif (in_array($addonId, $current, true)) {
            $current = array_values(array_diff($current, [$addonId]));
        } elseif (count($current) < $group->max_choices) {
            $current[] = $addonId;
        }

        $this->picked[$groupId] = $current;
    }

    public function confirmPick(): void
    {
        $product = $this->picking() ?? abort(404);
        $addons = [];

        foreach ($product->addonGroups as $group) {
            $chosen = $group->addons->whereIn('id', $this->picked[$group->id] ?? []);

            if ($chosen->count() < $group->min_choices) {
                $this->addError('picked', "Escolha em \"{$group->name}\" ({$group->rule()}).");

                return;
            }

            foreach ($chosen as $addon) {
                $addons[] = ['id' => $addon->id, 'name' => $addon->name, 'price' => $addon->price_cents->cents];
            }
        }

        $this->pushLine('product', $product->id, $product->name, $product->price_cents->cents, $addons, max(1, min(99, $this->pickQuantity)), trim($this->pickNotes));
        $this->pickingId = null;
    }

    public function setQuantity(string $key, int $quantity): void
    {
        foreach ($this->cart as $i => $line) {
            if ($line['key'] === $key) {
                if ($quantity <= 0) {
                    unset($this->cart[$i]);
                } else {
                    $this->cart[$i]['quantity'] = min(99, $quantity);
                }
            }
        }

        $this->cart = array_values($this->cart);
    }

    /** Linhas no formato de PlaceOrder. */
    protected function cartLines(): array
    {
        return array_map(fn (array $line) => [
            'type' => $line['type'],
            'id' => $line['id'],
            'quantity' => $line['quantity'],
            'addons' => array_column($line['addons'], 'id'),
            'notes' => $line['notes'] ?: null,
        ], $this->cart);
    }

    protected function cartSubtotal(): Money
    {
        return Money::cents(array_reduce(
            $this->cart,
            fn (int $sum, array $l) => $sum + ($l['unit'] + array_sum(array_column($l['addons'], 'price'))) * $l['quantity'],
            0,
        ));
    }

    /** Dados para as partials de produtos e do seletor de adicionais. */
    protected function pickerData(): array
    {
        return [
            'categories' => Category::active()->ordered()->get(),
            'products' => $this->sellable()
                ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
                ->search($this->search)
                ->orderBy('name')
                ->limit(60)
                ->get(),
            'combos' => trim($this->search) === '' && ! $this->categoryId
                ? Combo::where('is_active', true)->with('items.product')->orderBy('position')->get()->filter->isAvailable()
                : collect(),
            'picking' => $this->picking(),
        ];
    }

    private function pushLine(string $type, int $id, string $name, int $unit, array $addons, int $quantity, string $notes): void
    {
        $key = md5($type.$id.'|'.implode('.', array_column($addons, 'id')).'|'.$notes);

        foreach ($this->cart as $i => $line) {
            if ($line['key'] === $key) {
                $this->cart[$i]['quantity'] = min(99, $line['quantity'] + $quantity);

                return;
            }
        }

        $this->cart[] = compact('key', 'type', 'id', 'name', 'unit', 'addons', 'quantity', 'notes');
    }

    private function sellable(): Builder
    {
        return Product::query()->active()->where('is_available', true)
            ->with(['addonGroups' => fn ($q) => $q->where('is_active', true), 'addonGroups.addons' => fn ($q) => $q->where('is_active', true)]);
    }

    private function picking(): ?Product
    {
        return $this->pickingId ? $this->sellable()->find($this->pickingId) : null;
    }
}
