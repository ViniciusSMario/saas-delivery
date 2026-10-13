<?php

namespace App\Livewire\Tenant\Menu;

use App\Livewire\Tenant\Concerns\Toasts;
use App\Modules\Menu\Actions\SaveCategory;
use App\Modules\Menu\Models\Category;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Categorias')]
class Categories extends Component
{
    use Toasts;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['name' => 'nome', 'description' => 'descrição'];
    }

    public function edit(int $id): void
    {
        $category = Category::findOrFail($id);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->description = (string) $category->description;
        $this->is_active = $category->is_active;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'description', 'is_active');
        $this->resetErrorBag();
    }

    public function save(SaveCategory $save): void
    {
        $this->authorize('menu.manage');
        $this->validate();

        $save->handle(
            $this->editingId ? Category::findOrFail($this->editingId) : null,
            ['name' => $this->name, 'description' => $this->description ?: null, 'is_active' => $this->is_active],
        );

        $this->cancel();
    }

    public function move(int $id, int $direction, SaveCategory $save): void
    {
        $this->authorize('menu.manage');
        $save->move(Category::findOrFail($id), $direction < 0 ? -1 : 1);
    }

    public function toggle(int $id, SaveCategory $save): void
    {
        $this->authorize('menu.manage');
        $category = Category::findOrFail($id);
        $save->handle($category, ['name' => $category->name, 'is_active' => ! $category->is_active]);
        $category->refresh();
        $this->toast($category->is_active ? "Categoria {$category->name} ativada." : "Categoria {$category->name} desativada.", 'info', undo: 'toggle', params: [$id]);
    }

    public function delete(int $id, SaveCategory $save): void
    {
        $this->authorize('menu.manage');

        try {
            $save->delete(Category::findOrFail($id));
        } catch (ValidationException $e) {
            $this->addError('list', collect($e->errors())->flatten()->first());
        }
    }

    public function render()
    {
        return view('livewire.tenant.menu.categories', [
            'categories' => Category::ordered()->withCount('products')->get(),
        ]);
    }
}
