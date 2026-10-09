<?php

namespace App\Modules\Menu\Actions;

use App\Modules\Access\Audit;
use App\Modules\Menu\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveCategory
{
    /** @param  array{name: string, description?: string|null, is_active?: bool}  $data */
    public function handle(?Category $category, array $data): Category
    {
        $isNew = $category === null;
        $category ??= new Category(['position' => (Category::max('position') ?? 0) + 1]);
        $category->fill($data)->save();

        Audit::record($isNew ? 'category.created' : 'category.updated', $category, $isNew ? ['nome' => $category->name] : Audit::diff($category));

        return $category;
    }

    public function delete(Category $category): void
    {
        if ($category->products()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['category' => 'Mova ou exclua os produtos da categoria antes de excluí-la.']);
        }

        Audit::record('category.deleted', $category, ['nome' => $category->name]);
        $category->delete();
    }

    /** Sobe (-1) ou desce (+1) uma posição. */
    public function move(Category $category, int $direction): void
    {
        DB::connection('tenant')->transaction(function () use ($category, $direction) {
            $ordered = Category::ordered()->lockForUpdate()->get()->values();
            $index = $ordered->search(fn (Category $c) => $c->is($category));
            $target = $index + $direction;

            if ($index === false || $target < 0 || $target >= $ordered->count()) {
                return;
            }

            $items = $ordered->all();
            [$items[$index], $items[$target]] = [$items[$target], $items[$index]];

            foreach ($items as $position => $item) {
                $item->update(['position' => $position + 1]);
            }
        });
    }
}
