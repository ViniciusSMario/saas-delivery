<?php

namespace App\Modules\Menu\Import;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use App\Modules\Menu\Models\Addon;
use App\Modules\Menu\Models\AddonGroup;
use App\Modules\Menu\Models\Category;
use App\Modules\Imports\ImportRecorder;
use App\Modules\Imports\Models\DataImport;
use App\Modules\Menu\Models\Product;
use App\Modules\Menu\Models\ProductImage;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Grava o plano da importação (tudo ou nada) e desfaz a última importação.
 * Cada registro criado ou alterado fica em data_import_items (tipo "menu"), com os valores de antes.
 */
class MenuImporter
{
    private ImportRecorder $recorder;

    /** @var array<string, int> chave do nome => id */
    private array $categoryIds = [];

    /** @var array<string, int> */
    private array $groupIds = [];

    /** @var list<string> fotos gravadas no disco (apagadas se a transação falhar) */
    private array $stored = [];

    public function apply(MenuImportPlan $plan, string $mode, string $fileName, ?PhotoArchive $photos, ?string $photosName, ?User $user): DataImport
    {
        if ($plan->hasErrors()) {
            throw ValidationException::withMessages(['file' => 'A planilha tem erros. Corrija e envie de novo.']);
        }

        $this->stored = [];

        try {
            return DB::connection('tenant')->transaction(function () use ($plan, $mode, $fileName, $photos, $photosName, $user) {
                $this->recorder = new ImportRecorder;
                $import = $this->recorder->start('menu', $fileName, $mode, $user, $plan->counts(), $photosName);

                $this->categories($plan);
                $this->groups($plan);
                $photosSaved = $this->products($plan, $photos);

                $import->update(['summary' => ['photos' => $photosSaved] + $plan->counts()]);

                Audit::record('menu.imported', $import, [
                    'arquivo' => $import->file_name,
                    'produtos_criados' => $plan->counts()['products_created'],
                    'produtos_atualizados' => $plan->counts()['products_updated'],
                ], $user);

                return $import;
            });
        } catch (\Throwable $e) {
            Storage::disk('tenant')->delete($this->stored);

            throw $e;
        }
    }

    private function categories(MenuImportPlan $plan): void
    {
        $position = (int) Category::max('position');

        foreach ($plan->categories as $key => $category) {
            if ($category['id']) {
                $this->categoryIds[$key] = $category['id'];

                continue;
            }

            $model = Category::create(['name' => $category['name'], 'position' => ++$position]);
            $this->categoryIds[$key] = $model->id;
            $this->track('category', $model->id, 'created');
        }
    }

    private function groups(MenuImportPlan $plan): void
    {
        foreach ($plan->groups as $key => $group) {
            if ($group['action'] === 'create') {
                $model = AddonGroup::create(['name' => $group['name'], 'min_choices' => $group['min'], 'max_choices' => $group['max']]);
                $this->track('addon_group', $model->id, 'created');
            } else {
                $model = AddonGroup::findOrFail($group['id']);

                if ($group['action'] === 'update') {
                    $this->track('addon_group', $model->id, 'updated', $group['before']);
                    $model->update(['min_choices' => $group['min'], 'max_choices' => $group['max']]);
                }
            }

            $this->groupIds[$key] = $model->id;

            if (! in_array($group['action'], ['create', 'update'], true)) {
                continue;
            }

            $position = (int) $model->addons()->max('position');

            foreach ($group['options'] as $option) {
                if ($option['action'] === 'create') {
                    $addon = $model->addons()->create(['name' => $option['name'], 'price_cents' => Money::cents($option['price']), 'position' => ++$position]);
                    $this->track('addon', $addon->id, 'created');
                } elseif ($option['action'] === 'update') {
                    $this->track('addon', $option['id'], 'updated', ['price_cents' => $option['before']]);
                    Addon::whereKey($option['id'])->update(['price_cents' => $option['price']]);
                }
            }
        }

        // Grupos já cadastrados citados nos produtos, mas fora da aba Adicionais.
        foreach (AddonGroup::all(['id', 'name']) as $group) {
            $this->groupIds[MenuSpreadsheet::plain($group->name)] ??= $group->id;
        }
    }

    /** @return int fotos gravadas */
    private function products(MenuImportPlan $plan, ?PhotoArchive $photos): int
    {
        $positions = [];
        $photosSaved = 0;

        foreach ($plan->products as $row) {
            if (! in_array($row['action'], ['create', 'update'], true)) {
                continue;
            }

            $categoryId = $this->categoryIds[MenuSpreadsheet::plain($row['category'])];

            if ($row['action'] === 'create') {
                $positions[$categoryId] ??= (int) Product::where('category_id', $categoryId)->max('position');
                $product = Product::create([
                    'category_id' => $categoryId,
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'price_cents' => Money::cents($row['price']),
                    'is_available' => $row['available'] ?? true,
                    'is_featured' => $row['featured'] ?? false,
                    'position' => ++$positions[$categoryId],
                ]);
                $this->track('product', $product->id, 'created');
            } else {
                $product = Product::with('addonGroups:id')->findOrFail($row['id']);
                $this->track('product', $product->id, 'updated', [
                    'category_id' => $product->category_id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price_cents' => $product->price_cents->cents,
                    'is_available' => $product->is_available,
                    'is_featured' => $product->is_featured,
                    'addon_groups' => $product->addonGroups->pluck('id')->all(),
                ]);

                // Célula em branco não muda o que já existe.
                $product->fill(array_filter([
                    'category_id' => $categoryId,
                    'name' => $row['name'],
                    'code' => $row['code'],
                    'description' => $row['description'],
                    'price_cents' => $row['price'] !== null ? Money::cents($row['price']) : null,
                    'is_available' => $row['available'],
                    'is_featured' => $row['featured'],
                ], fn ($value) => $value !== null))->save();
            }

            if ($row['groups'] !== null) {
                $product->addonGroups()->sync(collect($row['groups'])
                    ->values()
                    ->mapWithKeys(fn (string $key, int $position) => [$this->groupIds[$key] => ['position' => $position]])
                    ->all());
            }

            if ($row['photo'] && $photos && ($file = $photos->read($row['photo']))) {
                [$bytes, $extension] = $file;
                $path = "products/{$product->id}/".Str::random(40).'.'.$extension;
                Storage::disk('tenant')->put($path, $bytes);
                $this->stored[] = $path;
                $image = $product->images()->create(['path' => $path, 'position' => (int) $product->images()->max('position') + 1]);
                $this->track('image', $image->id, 'created');
                $photosSaved++;
            }
        }

        return $photosSaved;
    }

    private function track(string $type, int $id, string $action, ?array $before = null): void
    {
        $this->recorder->track($type, $id, $action, $before);
    }

    // ---------- Desfazer ----------

    /** Só a última importação do cardápio ainda aplicada pode ser desfeita. */
    public static function canUndo(DataImport $import): bool
    {
        return $import->kind === 'menu' && $import->canUndo();
    }

    public function undo(DataImport $import, ?User $user): void
    {
        if (! self::canUndo($import)) {
            throw ValidationException::withMessages(['import' => 'Só a última importação pode ser desfeita.']);
        }

        $files = [];

        DB::connection('tenant')->transaction(function () use ($import, $user, &$files) {
            $items = $import->items()->orderByDesc('id')->get()->groupBy('subject_type');

            foreach ($items->get('image', collect()) as $item) {
                if ($image = ProductImage::find($item->subject_id)) {
                    $files[] = $image->path;
                    $image->delete();
                }
            }

            foreach ($items->get('product', collect()) as $item) {
                $product = Product::withTrashed()->find($item->subject_id);

                if (! $product) {
                    continue;
                }

                if ($item->action === 'created') {
                    $product->addonGroups()->detach();
                    $files = array_merge($files, $product->images()->pluck('path')->all());
                    $product->images()->delete();

                    // Já vendido (ou num combo): fica excluído, mas guardado para o histórico.
                    $used = DB::connection('tenant')->table('order_items')->where('product_id', $product->id)->exists()
                        || DB::connection('tenant')->table('combo_items')->where('product_id', $product->id)->exists();
                    $used ? $product->delete() : $product->forceDelete();
                } else {
                    $before = $item->before;
                    $product->forceFill(collect($before)->except('addon_groups')->all())->save();
                    $product->addonGroups()->sync(collect($before['addon_groups'] ?? [])
                        ->mapWithKeys(fn ($id, $position) => [$id => ['position' => $position]])->all());
                }
            }

            foreach ($items->get('addon', collect()) as $item) {
                $item->action === 'created'
                    ? Addon::whereKey($item->subject_id)->delete()
                    : Addon::whereKey($item->subject_id)->update($item->before);
            }

            foreach ($items->get('addon_group', collect()) as $item) {
                $group = AddonGroup::find($item->subject_id);

                if (! $group) {
                    continue;
                }

                if ($item->action === 'created') {
                    // Grupo ligado depois a outro produto pelo painel: só desativa.
                    $group->products()->exists() ? $group->update(['is_active' => false]) : $group->delete();
                } else {
                    $group->update($item->before);
                }
            }

            foreach ($items->get('category', collect()) as $item) {
                $category = Category::find($item->subject_id);

                if ($category) {
                    Product::withTrashed()->where('category_id', $category->id)->exists()
                        ? $category->update(['is_active' => false])
                        : $category->delete();
                }
            }

            $import->update(['status' => 'undone', 'undone_at' => now(), 'undone_by' => $user?->id]);
            Audit::record('menu.import_undone', $import, ['arquivo' => $import->file_name], $user);
        });

        Storage::disk('tenant')->delete($files);
    }
}
