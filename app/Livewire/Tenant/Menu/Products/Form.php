<?php

namespace App\Livewire\Tenant\Menu\Products;

use App\Modules\Menu\Actions\SaveProduct;
use App\Modules\Menu\Models\AddonGroup;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Menu\Models\ProductImage;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class Form extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $productId = null;

    public ?int $category_id = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public bool $is_active = true;

    public bool $is_available = true;

    public bool $is_featured = false;

    /** @var list<int> na ordem escolhida */
    public array $addonGroupIds = [];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $newImages = [];

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            $this->productId = $product->id;
            $this->category_id = $product->category_id;
            $this->code = (string) $product->code;
            $this->name = $product->name;
            $this->description = (string) $product->description;
            $this->price = $product->price_cents->decimal();
            $this->is_active = $product->is_active;
            $this->is_available = $product->is_available;
            $this->is_featured = $product->is_featured;
            $this->addonGroupIds = $product->addonGroups()->pluck('addon_groups.id')->all();
        } else {
            $this->category_id = (int) request()->query('categoria') ?: Category::ordered()->value('id');
        }
    }

    protected function rules(): array
    {
        return [
            'category_id' => ['required', Rule::exists('tenant.categories', 'id')],
            'code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-_.]+$/'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', fn ($attr, $value, $fail) => Money::isValid($value) && Money::parse($value)->cents >= 0
                ? null
                : $fail('Informe um preço válido, ex.: 39,90.')],
            'is_active' => ['boolean'],
            'is_available' => ['boolean'],
            'is_featured' => ['boolean'],
            'addonGroupIds' => ['array'],
            'addonGroupIds.*' => ['integer', Rule::exists('tenant.addon_groups', 'id')],
            'newImages' => ['array', 'max:'.SaveProduct::MAX_IMAGES],
            'newImages.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'category_id' => 'categoria', 'code' => 'código', 'name' => 'nome', 'description' => 'descrição',
            'price' => 'preço', 'newImages' => 'fotos', 'newImages.*' => 'foto',
        ];
    }

    public function updatedNewImages(): void
    {
        $this->validateOnly('newImages.*');
    }

    public function removeNewImage(int $index): void
    {
        unset($this->newImages[$index]);
        $this->newImages = array_values($this->newImages);
    }

    public function moveAddonGroup(int $id, int $direction): void
    {
        $index = array_search($id, $this->addonGroupIds, true);
        $target = $index + ($direction < 0 ? -1 : 1);

        if ($index === false || ! isset($this->addonGroupIds[$target])) {
            return;
        }

        [$this->addonGroupIds[$index], $this->addonGroupIds[$target]] = [$this->addonGroupIds[$target], $this->addonGroupIds[$index]];
    }

    public function removeImage(int $imageId, SaveProduct $save): void
    {
        $this->authorize('menu.manage');
        $save->removeImage(ProductImage::where('product_id', $this->productId)->findOrFail($imageId));
    }

    public function makeCover(int $imageId, SaveProduct $save): void
    {
        $this->authorize('menu.manage');
        $save->makeCover(ProductImage::where('product_id', $this->productId)->findOrFail($imageId));
    }

    public function save(SaveProduct $save): void
    {
        $this->authorize('menu.manage');
        $this->validate();

        $save->handle(
            $this->productId ? Product::findOrFail($this->productId) : null,
            [
                'category_id' => $this->category_id,
                'code' => $this->code,
                'name' => $this->name,
                'description' => $this->description ?: null,
                'price' => Money::parse($this->price),
                'is_active' => $this->is_active,
                'is_available' => $this->is_available,
                'is_featured' => $this->is_featured,
            ],
            array_map('intval', $this->addonGroupIds),
            $this->newImages,
        );

        session()->flash('status', 'Produto salvo.');
        $this->redirectRoute('menu.products.index', navigate: true);
    }

    public function render()
    {
        $groups = AddonGroup::withCount('addons')->orderBy('name')->get();

        return view('livewire.tenant.menu.products.form', [
            'categories' => Category::ordered()->get(),
            'images' => $this->productId ? ProductImage::where('product_id', $this->productId)->orderBy('position')->orderBy('id')->get() : collect(),
            'selectedGroups' => collect($this->addonGroupIds)->map(fn ($id) => $groups->firstWhere('id', $id))->filter()->values(),
            'availableGroups' => $groups->whereNotIn('id', $this->addonGroupIds)->values(),
            'maxImages' => SaveProduct::MAX_IMAGES,
        ])->title($this->productId ? 'Editar produto' : 'Novo produto');
    }
}
