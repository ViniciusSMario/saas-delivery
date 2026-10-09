<?php

namespace App\Modules\Menu\Actions;

use App\Modules\Access\Audit;
use App\Modules\Menu\Models\Product;
use App\Modules\Menu\Models\ProductImage;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SaveProduct
{
    public const MAX_IMAGES = 5;

    /**
     * @param  array{category_id: int, code?: string|null, name: string, description?: string|null, price: Money,
     *               is_active?: bool, is_available?: bool, is_featured?: bool}  $data
     * @param  list<int>  $addonGroupIds  na ordem em que aparecem para o cliente
     * @param  list<UploadedFile>  $newImages
     */
    public function handle(?Product $product, array $data, array $addonGroupIds = [], array $newImages = []): Product
    {
        $isNew = $product === null;
        $product ??= new Product;

        $code = isset($data['code']) && trim($data['code']) !== '' ? trim($data['code']) : null;

        if ($code && Product::query()->whereRaw('lower(code) = lower(?)', [$code])->whereKeyNot($product->getKey())->exists()) {
            throw ValidationException::withMessages(['code' => 'Já existe um produto com este código.']);
        }

        $existingImages = $product->exists ? $product->images()->count() : 0;

        if ($existingImages + count($newImages) > self::MAX_IMAGES) {
            throw ValidationException::withMessages(['newImages' => 'Cada produto pode ter até '.self::MAX_IMAGES.' fotos.']);
        }

        $stored = [];

        try {
            DB::connection('tenant')->transaction(function () use ($product, $data, $code, $addonGroupIds, $newImages, $isNew, &$stored) {
                if ($isNew) {
                    $product->position = (Product::where('category_id', $data['category_id'])->max('position') ?? 0) + 1;
                }

                $product->fill([
                    'category_id' => $data['category_id'],
                    'code' => $code,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'price_cents' => $data['price'],
                    'is_active' => $data['is_active'] ?? true,
                    'is_available' => $data['is_available'] ?? true,
                    'is_featured' => $data['is_featured'] ?? false,
                ])->save();

                $product->addonGroups()->sync(collect($addonGroupIds)
                    ->values()
                    ->mapWithKeys(fn (int $id, int $position) => [$id => ['position' => $position]])
                    ->all());

                $position = (int) $product->images()->max('position');

                foreach ($newImages as $file) {
                    // Caminho gerado aqui, dentro do disco do tenant.
                    $stored[] = $path = $file->store("products/{$product->id}", 'tenant');
                    $product->images()->create(['path' => $path, 'position' => ++$position]);
                }

                $changes = $isNew ? ['nome' => $product->name, 'preco' => $product->price_cents->format()] : Audit::diff($product);

                if ($newImages !== []) {
                    $changes['fotos_adicionadas'] = count($newImages);
                }

                Audit::record($isNew ? 'product.created' : 'product.updated', $product, $changes);
            });
        } catch (\Throwable $e) {
            // Se a transação falhou, as fotos gravadas no disco não ficam órfãs.
            Storage::disk('tenant')->delete($stored);

            throw $e;
        }

        return $product;
    }

    public function removeImage(ProductImage $image): void
    {
        Storage::disk('tenant')->delete($image->path);
        $image->delete();
        Audit::record('product.updated', $image->product, ['foto_removida' => $image->id]);
    }

    /** A foto escolhida vira a capa (primeira posição). */
    public function makeCover(ProductImage $image): void
    {
        DB::connection('tenant')->transaction(function () use ($image) {
            $others = $image->product->images()->whereKeyNot($image->id)->get();
            $image->update(['position' => 0]);

            foreach ($others as $i => $other) {
                $other->update(['position' => $i + 1]);
            }
        });
    }

    public function delete(Product $product): void
    {
        if ($product->trashed()) {
            return;
        }

        // Soft delete: pedidos antigos continuam apontando para o produto. As fotos ficam,
        // para o caso de restauração. O código fica livre (o índice único ignora excluídos).
        $product->delete();
        Audit::record('product.deleted', $product, ['nome' => $product->name]);
    }

    public function toggleAvailability(Product $product): void
    {
        $product->update(['is_available' => ! $product->is_available]);
        Audit::record('product.updated', $product, ['esgotado' => ! $product->is_available]);
    }
}
