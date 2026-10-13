<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Imports\TabularImporter;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Modelo e dados atuais (clientes, bairros, insumos, fichas) no formato da importação. */
class DataSpreadsheetController
{
    public function template(string $type, string $format, TabularImporter $importer): BinaryFileResponse
    {
        return $this->download($type, $format, false, $importer);
    }

    public function export(string $type, string $format, TabularImporter $importer): BinaryFileResponse
    {
        return $this->download($type, $format, true, $importer);
    }

    private function download(string $slug, string $format, bool $current, TabularImporter $importer): BinaryFileResponse
    {
        $type = TabularImporter::type($slug);
        Gate::authorize($type->permission());

        $path = tempnam(sys_get_temp_dir(), 'dados').'.'.$format;
        $importer->write($type, $path, $format, $current);
        $name = ($current ? $slug.'-'.now()->format('Y-m-d') : 'modelo-'.$slug).'.'.$format;

        return response()->download($path, $name)->deleteFileAfterSend();
    }
}
