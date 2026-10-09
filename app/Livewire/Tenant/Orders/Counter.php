<?php

namespace App\Livewire\Tenant\Orders;

use App\Modules\Access\Models\User;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Combo;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Models\PaymentMethod;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Venda de balcão (e pedido por telefone). O PDV completo, com caixa, vem na fase 5. */
#[Title('Venda no balcão')]
class Counter extends Component
{
    public string $search = '';

    public ?int $categoryId = null;

    /** @var list<array{key: string, type: string, id: int, name: string, unit: int, addons: list<array{id: int, name: string, price: int}>, quantity: int, notes: string}> */
    public array $cart = [];

    // Escolha de adicionais
    public ?int $pickingId = null;

    /** @var array<int, list<int>> groupId => addonIds */
    public array $picked = [];

    public int $pickQuantity = 1;

    public string $pickNotes = '';

    // Fechamento
    public string $fulfillment = 'counter';

    public string $customerName = '';

    public string $customerPhone = '';

    /** @var array<string, string> */
    public array $address = ['street' => '', 'number' => '', 'complement' => '', 'district' => '', 'city' => '', 'reference' => ''];

    public string $paymentMethod = '';

    public string $changeFor = '';

    public string $discount = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->paymentMethod = (string) PaymentMethod::active()->value('code');
    }

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

    public function clearCart(): void
    {
        $this->reset('cart', 'customerName', 'customerPhone', 'address', 'changeFor', 'discount', 'notes');
        $this->fulfillment = 'counter';
        $this->resetErrorBag();
    }

    public function checkout(PlaceOrder $placeOrder): void
    {
        $this->authorize('pos.use');
        $this->resetErrorBag();

        foreach (['changeFor' => $this->changeFor, 'discount' => $this->discount] as $field => $value) {
            if ($value !== '' && ! Money::isValid($value)) {
                $this->addError($field, 'Valor inválido.');

                return;
            }
        }

        try {
            $order = $placeOrder->handle([
                'lines' => array_map(fn (array $line) => [
                    'type' => $line['type'],
                    'id' => $line['id'],
                    'quantity' => $line['quantity'],
                    'addons' => array_column($line['addons'], 'id'),
                    'notes' => $line['notes'] ?: null,
                ], $this->cart),
                'fulfillment' => $this->fulfillment,
                'customer' => ['name' => $this->customerName, 'phone' => $this->customerPhone],
                'address' => $this->address,
                'payment_method' => $this->paymentMethod,
                'change_for' => $this->changeFor !== '' ? Money::parse($this->changeFor) : null,
                'discount' => $this->discount !== '' ? Money::parse($this->discount) : null,
                'notes' => $this->notes,
            ], OrderSource::Counter, $this->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError(str_starts_with($key, 'lines') ? 'cart' : $key, $messages[0]);
            }

            return;
        }

        $this->clearCart();
        session()->flash('status', "Pedido #{$order->number} criado · {$order->total_cents->format()}.");
        $this->redirectRoute('orders.counter', navigate: true);
    }

    private function pushLine(string $type, int $id, string $name, int $unit, array $addons, int $quantity, string $notes): void
    {
        $signature = $type.$id.'|'.implode('.', array_column($addons, 'id')).'|'.$notes;

        foreach ($this->cart as $i => $line) {
            if ($line['key'] === md5($signature)) {
                $this->cart[$i]['quantity'] = min(99, $line['quantity'] + $quantity);

                return;
            }
        }

        $this->cart[] = compact('type', 'id', 'name', 'unit', 'addons', 'quantity', 'notes') + ['key' => md5($signature)];
    }

    private function sellable()
    {
        return Product::query()->active()->where('is_available', true)
            ->with(['addonGroups' => fn ($q) => $q->where('is_active', true), 'addonGroups.addons' => fn ($q) => $q->where('is_active', true)]);
    }

    private function picking(): ?Product
    {
        return $this->pickingId ? $this->sellable()->find($this->pickingId) : null;
    }

    private function user(): User
    {
        return Auth::guard('web')->user();
    }

    public function render()
    {
        $subtotal = array_reduce($this->cart, fn (int $sum, array $l) => $sum + ($l['unit'] + array_sum(array_column($l['addons'], 'price'))) * $l['quantity'], 0);
        $fee = $this->fulfillment === 'delivery' ? (int) Setting::read('delivery_fee_cents', 0) : 0;
        $discount = $this->discount !== '' && Money::isValid($this->discount) ? Money::parse($this->discount)->cents : 0;

        return view('livewire.tenant.orders.counter', [
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
            'paymentMethods' => PaymentMethod::active()->get(),
            'subtotal' => Money::cents($subtotal),
            'fee' => Money::cents($fee),
            'total' => Money::cents(max(0, $subtotal - $discount + $fee)),
            'canDiscount' => $this->user()->can('orders.discount'),
        ]);
    }
}
