<?php

use App\Modules\Establishment\Models\Setting;
use App\Platform\Backups\TenantBackups;
use App\Platform\Models\TenantBackup;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['filesystems.disks.backups.root' => storage_path('framework/testing/backups')]);
    Storage::forgetDisk('backups');
    Storage::fake('s3');
    config(['platform.backups.offsite_disk' => 's3']);
});

afterEach(fn () => File::deleteDirectory(storage_path('framework/testing/backups')));

it('copia o backup para o bucket e restaura de lá quando a cópia local sumiu', function () {
    $tenant = provisionTenant('off');
    inTenant($tenant, fn () => Setting::write('welcome_message', 'no bucket'));

    $backups = app(TenantBackups::class);
    $backup = $backups->create($tenant, 'manual', 'teste');

    expect($backup->offsite_at)->not->toBeNull();
    Storage::disk('s3')->assertExists($backup->path.'/database.dump');
    Storage::disk('s3')->assertExists($backup->path.'/manifest.json');

    // Servidor novo: a pasta local não existe mais.
    Storage::disk('backups')->deleteDirectory($backup->path);
    inTenant($tenant, fn () => Setting::write('welcome_message', 'mudou'));

    $backups->restore($backup->refresh(), 'teste');

    expect(inTenant($tenant, fn () => Setting::read('welcome_message')))->toBe('no bucket');
});

it('apaga do bucket o que sai da retenção', function () {
    $tenant = provisionTenant('offprune');
    $backup = app(TenantBackups::class)->create($tenant, 'manual', 'teste');
    $backup->update(['created_at' => now()->subDays(45)]);

    app(TenantBackups::class)->prune();

    expect(TenantBackup::find($backup->id))->toBeNull();
    Storage::disk('s3')->assertMissing($backup->path.'/database.dump');
});

it('faz o backup do banco inteiro, manda para o bucket e mantém só os mais recentes', function () {
    config(['platform.backups.keep_database' => 2]);

    foreach (range(1, 3) as $i) {
        $this->travel(1)->minutes();
        $this->artisan('platform:backup-database')->assertSuccessful();
    }

    $local = Storage::disk('backups')->files('database');
    expect($local)->toHaveCount(2)
        ->and(Storage::disk('s3')->files('database'))->toHaveCount(2)
        ->and(Storage::disk('backups')->size($local[0]))->toBeGreaterThan(1000);
});
