<?php

namespace App\Modules\Exports\Jobs;

use App\Modules\Exports\Models\DataExport;
use App\Modules\Exports\Notifications\DataExportReady;
use App\Modules\Exports\TenantDataExporter;
use App\Tenancy\Jobs\TenantAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BuildDataExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, TenantAware;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $exportId)
    {
    }

    public function handle(TenantDataExporter $exporter): void
    {
        $export = DataExport::find($this->exportId);

        if (! $export || $export->status !== 'pending') {
            return;
        }

        try {
            $path = $exporter->build($export->id);
        } catch (Throwable $e) {
            $export->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500), 'completed_at' => now()]);
            report($e);

            return;
        }

        $export->update(['status' => 'done', 'path' => $path, 'size_bytes' => Storage::disk('tenant')->size($path), 'completed_at' => now()]);

        // Arquivos vencidos saem do disco.
        DataExport::where('status', 'done')->where('completed_at', '<', now()->subDays(DataExport::KEEP_DAYS))->get()
            ->each(function (DataExport $old) {
                Storage::disk('tenant')->delete((string) $old->path);
                $old->update(['path' => null]);
            });

        $export->requester?->notify(new DataExportReady);
    }
}
