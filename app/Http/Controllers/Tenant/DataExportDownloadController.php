<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Access\Audit;
use App\Modules\Exports\Models\DataExport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Download da exportação de dados (só o dono). */
class DataExportDownloadController
{
    public function __invoke(DataExport $export): StreamedResponse
    {
        Gate::authorize('billing.manage');
        abort_unless($export->isAvailable() && $export->path && Storage::disk('tenant')->exists($export->path), 404);

        Audit::record('data.export_downloaded', $export);

        return Storage::disk('tenant')->download($export->path, 'dados-'.tenant()->slug.'-'.$export->completed_at->format('Ymd').'.zip');
    }
}
