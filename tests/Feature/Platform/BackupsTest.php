<?php

use App\Livewire\Platform\Tenants\Show;
use App\Modules\Establishment\Models\Setting;
use App\Platform\Actions\TenantDeletion;
use App\Platform\Backups\TenantBackups;
use App\Platform\Models\TenantBackup;
use App\Platform\Notifications\TenantNotice;
use App\Tenancy\Enums\TenantStatus;
use App\Tenancy\Models\Tenant;
use App\Tenancy\SchemaManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    // Backups dos testes numa pasta própria, apagada no fim.
    config(['filesystems.disks.backups.root' => storage_path('framework/testing/backups')]);
    Storage::forgetDisk('backups');
});

afterEach(function () {
    File::deleteDirectory(storage_path('framework/testing/backups'));
});

function tenantFile(Tenant $tenant, string $name): string
{
    return storage_path("app/tenants/{$tenant->id}/{$name}");
}

it('faz backup do schema e dos arquivos do estabelecimento', function () {
    $tenant = provisionTenant('bkp');
    File::ensureDirectoryExists(dirname(tenantFile($tenant, 'logo.txt')));
    File::put(tenantFile($tenant, 'logo.txt'), 'logo');

    $backup = app(TenantBackups::class)->create($tenant, 'manual', 'teste');

    expect($backup->status)->toBe('done')
        ->and($backup->size_bytes)->toBeGreaterThan(0)
        ->and($backup->schema_version)->toBe($tenant->schema_version);

    $disk = Storage::disk('backups');
    expect($disk->exists($backup->path.'/database.dump'))->toBeTrue()
        ->and($disk->exists($backup->path.'/files.tar.gz'))->toBeTrue()
        ->and(json_decode($disk->get($backup->path.'/manifest.json'), true)['slug'])->toBe($tenant->slug);
});

it('restaura dados e arquivos e guarda o estado anterior', function () {
    $tenant = provisionTenant('rst');
    inTenant($tenant, fn () => Setting::write('welcome_message', 'versão do backup'));
    File::ensureDirectoryExists(dirname(tenantFile($tenant, 'foto.txt')));
    File::put(tenantFile($tenant, 'foto.txt'), 'original');

    $backups = app(TenantBackups::class);
    $backup = $backups->create($tenant, 'manual', 'teste');

    inTenant($tenant, fn () => Setting::write('welcome_message', 'mudou depois'));
    File::put(tenantFile($tenant, 'foto.txt'), 'alterada');
    File::put(tenantFile($tenant, 'nova.txt'), 'nova');

    $backups->restore($backup, 'teste');
    $tenant->refresh();

    expect($tenant->status)->toBe(TenantStatus::Active)
        ->and(inTenant($tenant, fn () => Setting::read('welcome_message')))->toBe('versão do backup')
        ->and(File::get(tenantFile($tenant, 'foto.txt')))->toBe('original')
        ->and(File::exists(tenantFile($tenant, 'nova.txt')))->toBeFalse()
        ->and(TenantBackup::where('tenant_id', $tenant->id)->where('kind', 'pre_restore')->exists())->toBeTrue()
        // Nenhum schema "estacionado" sobra.
        ->and(collect(app(SchemaManager::class)->all())->filter(fn ($s) => str_starts_with($s, substr($tenant->schema_name, 0, 26)))->values()->all())
        ->toBe([$tenant->schema_name]);

    // O app volta a responder normalmente.
    $this->get(tenantUrl($tenant, '/cardapio'))->assertOk()->assertSee('versão do backup');
});

it('não perde nada quando a restauração falha', function () {
    $tenant = provisionTenant('rstfail');
    inTenant($tenant, fn () => Setting::write('welcome_message', 'dado atual'));
    $backups = app(TenantBackups::class);
    $backup = $backups->create($tenant, 'manual', 'teste');

    Storage::disk('backups')->put($backup->path.'/database.dump', 'arquivo corrompido');

    expect(fn () => $backups->restore($backup, 'teste'))->toThrow(RuntimeException::class);

    $tenant->refresh();
    expect($tenant->status)->toBe(TenantStatus::Active)
        ->and(inTenant($tenant, fn () => Setting::read('welcome_message')))->toBe('dado atual')
        ->and($tenant->events()->where('type', 'restore_failed')->exists())->toBeTrue();
});

it('mantém os diários recentes, os de domingo e apaga o resto', function () {
    $tenant = provisionTenant('ret');
    $now = now()->startOfWeek()->addDays(2)->setTime(12, 0); // uma quarta-feira

    $make = fn (string $kind, $date, string $status = 'done') => TenantBackup::create([
        'tenant_id' => $tenant->id, 'kind' => $kind, 'status' => $status, 'created_at' => $date,
    ]);

    $daily = collect(range(0, 20))->map(fn ($days) => $make('daily', $now->copy()->subDays($days)));
    $oldManual = $make('manual', $now->copy()->subDays(40));
    $recentManual = $make('manual', $now->copy()->subDays(3));
    $oldFailed = $make('daily', $now->copy()->subDays(10), 'failed');

    app(TenantBackups::class)->prune($now);

    $left = TenantBackup::where('tenant_id', $tenant->id)->pluck('id');
    $keptDaily = $daily->filter(fn ($b) => $left->contains($b->id));

    expect($keptDaily->take(7)->pluck('id')->all())->toBe($daily->take(7)->pluck('id')->all())
        ->and($keptDaily->slice(7)->every(fn ($b) => $b->created_at->isSunday()))->toBeTrue()
        ->and($keptDaily->count())->toBe(7 + $daily->slice(7)->filter(fn ($b) => $b->created_at->isSunday())->count())
        ->and($left)->not->toContain($oldManual->id)
        ->and($left)->toContain($recentManual->id)
        ->and($left)->not->toContain($oldFailed->id);
});

it('agenda a exclusão, bloqueia o acesso e permite cancelar', function () {
    Notification::fake();
    $tenant = provisionTenant('del');
    createTenantUser($tenant, ['dono'], ['email' => 'dono-del@teste.com']);

    app(TenantDeletion::class)->schedule($tenant, 'equipe@saas.test');

    expect($tenant->refresh()->status)->toBe(TenantStatus::PendingDeletion)
        ->and($tenant->deletion_scheduled_at->isSameDay(now()->addDays(30)))->toBeTrue();
    $this->get(tenantUrl($tenant, '/cardapio'))->assertForbidden()->assertSee('Conta em exclusão');
    Notification::assertSentOnDemand(TenantNotice::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'dono-del@teste.com');

    app(TenantDeletion::class)->cancel($tenant, 'equipe@saas.test');

    expect($tenant->refresh()->status)->toBe(TenantStatus::Active)->and($tenant->deletion_scheduled_at)->toBeNull();
    $this->get(tenantUrl($tenant, '/cardapio'))->assertOk();
});

it('remove de vez depois da carência, com backup final', function () {
    Notification::fake();
    $tenant = provisionTenant('purge');
    $domain = $tenant->primaryDomain();
    app(TenantDeletion::class)->schedule($tenant, 'equipe@saas.test');

    // Antes do prazo nada acontece.
    $this->artisan('tenants:purge-deleted')->assertSuccessful();
    expect(app(SchemaManager::class)->exists($tenant->schema_name))->toBeTrue();

    $this->travel(31)->days();
    $this->artisan('tenants:purge-deleted')->assertSuccessful();

    $trashed = Tenant::withTrashed()->find($tenant->id);
    expect($trashed->status)->toBe(TenantStatus::Deleted)
        ->and($trashed->trashed())->toBeTrue()
        ->and(app(SchemaManager::class)->exists($tenant->schema_name))->toBeFalse()
        ->and(TenantBackup::where('tenant_id', $tenant->id)->where('kind', 'final')->where('status', 'done')->exists())->toBeTrue();

    $this->get('http://'.$domain.'/cardapio')->assertNotFound();
});

it('opera backups e exclusão pelo admin da plataforma', function () {
    Notification::fake();
    $tenant = provisionTenant('adm');
    $admin = createPlatformUser();

    $this->actingAs($admin, 'platform');
    Livewire::test(Show::class, ['tenant' => $tenant])
        ->call('backupNow')
        ->assertHasNoErrors();

    $backup = TenantBackup::where('tenant_id', $tenant->id)->firstOrFail();
    expect($backup->created_by)->toBe($admin->email);

    $this->get('http://admin.localhost/backups/'.$backup->id.'/download')->assertOk()->assertDownload();

    Livewire::test(Show::class, ['tenant' => $tenant])
        ->set('confirmSlug', 'errado')->call('scheduleDeletion')->assertHasErrors('confirmSlug')
        ->set('confirmSlug', $tenant->slug)->call('scheduleDeletion')->assertHasNoErrors();
    expect($tenant->refresh()->status)->toBe(TenantStatus::PendingDeletion);

    Livewire::test(Show::class, ['tenant' => $tenant])->call('cancelDeletion');
    expect($tenant->refresh()->status)->toBe(TenantStatus::Active);

    // Suporte só consulta.
    $this->actingAs(createPlatformUser('support'), 'platform');
    Livewire::test(Show::class, ['tenant' => $tenant])->call('backupNow')->assertForbidden();
});

it('tenants:backup e tenants:restore funcionam pelo terminal', function () {
    $tenant = provisionTenant('cli');
    inTenant($tenant, fn () => Setting::write('welcome_message', 'antes'));

    $this->artisan('tenants:backup', ['--tenant' => $tenant->slug])->assertSuccessful();
    $backup = TenantBackup::where('tenant_id', $tenant->id)->firstOrFail();

    inTenant($tenant, fn () => Setting::write('welcome_message', 'depois'));
    $this->artisan('tenants:restore', ['tenant' => $tenant->slug, '--backup' => $backup->id, '--force' => true])->assertSuccessful();

    expect(inTenant($tenant, fn () => Setting::read('welcome_message')))->toBe('antes');
});
