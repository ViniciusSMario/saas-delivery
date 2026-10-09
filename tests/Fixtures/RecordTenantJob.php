<?php

namespace Tests\Fixtures;

use App\Modules\Establishment\Models\Setting;
use App\Tenancy\Jobs\TenantAware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/** Grava no tenant em que rodou qual marcador recebeu e qual schema estava ativo. */
class RecordTenantJob implements ShouldQueue
{
    use Queueable, TenantAware;

    public function __construct(public string $marker)
    {
    }

    public function handle(): void
    {
        Setting::write('job_marker', $this->marker);
        Setting::write('job_schema', DB::connection('tenant')->scalar('select current_schema()'));
    }
}
