<?php

namespace App\Livewire\Tenant\Menu\Combos;

use App\Modules\Menu\Actions\SaveCombo;
use App\Modules\Menu\Models\Combo;
use App\Modules\Menu\Models\Product;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class Form extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $comboId = null;

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public bool $is_active = true;

    /** @var list<array{product_id: int|string|null, quantity: int}> */
    public array $items = [];

    public $image;

    public bool $hasImage = false;

    public function mount(?Combo $combo = null): void
    {
        if ($combo?->exists) {
            $this->comboId = $combo->id;
            $this->name = $combo->name;
            $this->description = (string) $combo->description;
            $this->price = $combo->price_cents->decimal();
            $this->is_active = $combo->is_active;
            $this->hasImage = (bool) $combo->image_path;
            $this->items = $combo->items->map(fn ($i) => ['product_id' => $i->product_id, 'quantity' => $i->quantity])->all();
        }

        if ($this->items === []) {
            $this->addItem();
        }
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => null, 'quantity' => 1];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', fn ($attr, $value, $fail) => Money::isValid($value) && Money::parse($value)->cents >= 0
                ? null
                : $fail('Informe um preço válido, ex.: 59,90.')],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_id' => ['required', Rule::exists('tenant.products', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'nome', 'description' => 'descrição', 'price' => 'preço', 'image' => 'foto',
            'items' => 'itens', 'items.*.product_id' => 'produto', 'items.*.quantity' => 'quantidade',
        ];
    }

    public function save(SaveCombo $save): void
    {
        $this->authorize('menu.manage');
        $this->validate();

        $save->handle(
            $this->comboId ? Combo::findOrFail($this->comboId) : null,
            ['name' => $this->name, 'description' => $this->description ?: null, 'price' => Money::parse($this->price), 'is_active' => $this->is_active],
            array_map(fn (array $row) => ['product_id' => (int) $row['product_id'], 'quantity' => (int) $row['quantity']], $this->items),
            $this->image,
        );

        session()->flash('status', 'Combo salvo.');
        $this->redirectRoute('menu.combos.index', navigate: true);
    }

    public function render()
    {
        $products = Product::with('category')->orderBy('name')->get();
        $regular = collect($this->items)->reduce(function (Money $total, array $row) use ($products) {
            $product = $products->firstWhere('id', (int) $row['product_id']);

            return $product ? $total->add($product->price_cents->multiply(max(1, (int) $row['quantity']))) : $total;
        }, Money::zero());

        return view('livewire.tenant.menu.combos.form', [
            'products' => $products,
            'regular' => $regular,
            'priceValue' => Money::isValid($this->price) ? Money::parse($this->price) : null,
        ])->title($this->comboId ? 'Editar combo' : 'Novo combo');
    }
}
