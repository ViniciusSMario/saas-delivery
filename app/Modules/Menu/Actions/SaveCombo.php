<?php

namespace App\Modules\Menu\Actions;

use App\Modules\Access\Audit;
use App\Modules\Menu\Models\Combo;
use App\Modules\Menu\Models\Product;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SaveCombo
{
    /**
     * @param  array{name: string, description?: string|null, price: Money, is_active?: bool}  $data
     * @param  list<array{product_id: int, quantity: int}>  $items
     */
    public function handle(?Combo $combo, array $data, array $items, ?UploadedFile $image = null): Combo
    {
        $items = collect($items)
            ->groupBy('product_id')
            ->map(fn ($rows, $productId) => ['product_id' => (int) $productId, 'quantity' => (int) $rows->sum('quantity')])
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Adicione pelo menos um produto ao combo.']);
        }

        if (Product::whereKey($items->pluck('product_id'))->count() !== $items->count()) {
            throw ValidationException::withMessages(['items' => 'Um dos produtos do combo não existe mais.']);
        }

        $isNew = $combo === null;
        $combo ??= new Combo(['position' => (Combo::max('position') ?? 0) + 1]);
        $oldImage = $combo->image_path;
        $newPath = $image?->store('combos', 'tenant');

        try {
            DB::connection('tenant')->transaction(function () use ($combo, $data, $items, $newPath, $isNew) {
                $combo->fill([
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'price_cents' => $data['price'],
                    'is_active' => $data['is_active'] ?? true,
                ]);

                if ($newPath) {
                    $combo->image_path = $newPath;
                }

                $combo->save();
                $combo->items()->delete();
                $combo->items()->createMany($items->all());

                Audit::record($isNew ? 'combo.created' : 'combo.updated', $combo, [
                    'nome' => $combo->name,
                    'preco' => $combo->price_cents->format(),
                    'itens' => $items->count(),
                ]);
            });
        } catch (\Throwable $e) {
            $newPath && Storage::disk('tenant')->delete($newPath);

            throw $e;
        }

        if ($newPath && $oldImage) {
            Storage::disk('tenant')->delete($oldImage);
        }

        return $combo;
    }

    public function delete(Combo $combo): void
    {
        $combo->delete();
        Audit::record('combo.deleted', $combo, ['nome' => $combo->name]);
    }
}
