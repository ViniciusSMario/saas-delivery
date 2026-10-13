<?php

namespace App\Livewire\Tenant\Menu\Products;

use App\Livewire\Tenant\Concerns\Toasts;
use App\Modules\Menu\Actions\SaveProduct;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Produtos')]
class Index extends Component
{
    use Toasts, WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'categoria')]
    public ?int $categoryId = null;

    #[Url]
    public string $status = '';

    public function updated($property): void
    {
        if (in_array($property, ['search', 'categoryId', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'categoryId', 'status');
        $this->resetPage();
    }

    public function toggleAvailability(int $id, SaveProduct $save): void
    {
        $this->authorize('menu.manage');
        $save->toggleAvailability($product = Product::findOrFail($id));
        $this->toast($product->is_available ? "{$product->name} voltou ao cardápio." : "{$product->name} marcado como esgotado.", 'info', undo: 'toggleAvailability', params: [$id]);
    }

    public function delete(int $id, SaveProduct $save): void
    {
        $this->authorize('menu.manage');
        $save->delete(Product::findOrFail($id));
    }

    public function render()
    {
        $products = Product::query()
            ->with(['category', 'cover'])
            ->search($this->search)
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->status === 'sold_out', fn ($q) => $q->where('is_available', false))
            ->orderBy(Category::select('position')->whereColumn('categories.id', 'products.category_id'))
            ->orderBy('position')
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.tenant.menu.products.index', [
            'products' => $products,
            'categories' => Category::ordered()->get(),
        ]);
    }
}
