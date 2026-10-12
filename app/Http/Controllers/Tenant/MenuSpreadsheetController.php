<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Menu\Import\MenuSpreadsheet;
use App\Modules\Menu\Models\AddonGroup;
use App\Modules\Menu\Models\Category;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Modelo da planilha do cardápio e exportação do cardápio atual no mesmo formato (para editar e importar). */
class MenuSpreadsheetController
{
    public function template(string $format, MenuSpreadsheet $sheet): BinaryFileResponse
    {
        return $this->download($format, 'modelo-cardapio', fn (string $path) => $format === 'xlsx' ? $sheet->writeXlsx($path) : $sheet->writeCsv($path));
    }

    public function export(string $format, MenuSpreadsheet $sheet): BinaryFileResponse
    {
        $categories = Category::ordered()->with(['products' => fn ($q) => $q->active()->with('addonGroups')])->get()
            ->filter(fn (Category $category) => $category->products->isNotEmpty());
        $groups = AddonGroup::with('addons')->orderBy('name')->get();

        return $this->download($format, 'cardapio-'.now()->format('Y-m-d'), fn (string $path) => $format === 'xlsx'
            ? $sheet->writeXlsx($path, $categories, $groups)
            : $sheet->writeCsv($path, $categories));
    }

    private function download(string $format, string $name, callable $write): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'cardapio').'.'.$format;
        $write($path);

        return response()->download($path, "{$name}.{$format}")->deleteFileAfterSend();
    }
}
