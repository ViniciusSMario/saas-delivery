<?php

use App\Modules\Establishment\Models\Setting;
use App\Tenancy\Exceptions\MissingTenantForJob;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Tests\Fixtures\RecordTenantJob;

it('roda o job de A no schema de A mesmo logo depois de um job de B', function () {
    $a = tenantBySlug('teste1');
    $b = tenantBySlug('teste2');

    inTenant($b, function () {
        RecordTenantJob::dispatch('B')->onConnection('redis')->onQueue('tenancy-test');
    });
    inTenant($a, function () {
        RecordTenantJob::dispatch('A')->onConnection('redis')->onQueue('tenancy-test');
    });

    // Um worker processa B e depois A, no mesmo processo.
    Artisan::call('queue:work', [
        'connection' => 'redis',
        '--queue' => 'tenancy-test',
        '--stop-when-empty' => true,
        '--tries' => 1,
    ]);

    expect(inTenant($a, fn () => [Setting::read('job_marker'), Setting::read('job_schema')]))
        ->toBe(['A', $a->schema_name])
        ->and(inTenant($b, fn () => [Setting::read('job_marker'), Setting::read('job_schema')]))
        ->toBe(['B', $b->schema_name])
        ->and(app(TenantContext::class)->check())->toBeFalse();
});

it('recusa despachar job de tenant sem tenant ativo', function () {
    expect(function () {
        RecordTenantJob::dispatch('sem-tenant')->onConnection('redis')->onQueue('tenancy-test');
    })->toThrow(MissingTenantForJob::class);
});
