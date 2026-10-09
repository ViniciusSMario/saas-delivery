<?php

use App\Livewire\Tenant\Establishment\EditEstablishment;
use App\Livewire\Tenant\Establishment\EditSettings;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Establishment\Models\Establishment;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Establishment\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->a = tenantBySlug('teste1');
    $this->b = tenantBySlug('teste2');
});

it('salva dados, logo e horários do estabelecimento', function () {
    $owner = createTenantUser($this->a);
    activateTenant($this->a);

    Livewire::actingAs($owner)->test(EditEstablishment::class)
        ->set('form.trade_name', 'Pizzaria do Teste')
        ->set('form.document', '12.345.678/0001-90')
        ->set('form.city', 'Campinas')
        ->set('form.state', 'sp')
        ->set('logo', UploadedFile::fake()->image('logo.png', 200, 200))
        ->set('hours.1.is_closed', true)
        ->set('hours.5.opens_at', '18:00')
        ->set('hours.5.closes_at', '02:00')
        ->call('save')
        ->assertHasNoErrors();

    $establishment = Establishment::current();

    expect($establishment->trade_name)->toBe('Pizzaria do Teste')
        ->and($establishment->state)->toBe('SP')
        ->and($establishment->logo_path)->toStartWith('establishment/')
        ->and(Storage::disk('tenant')->exists($establishment->logo_path))->toBeTrue()
        ->and(Storage::disk('tenant')->path($establishment->logo_path))->toContain('tenants/'.$this->a->id.'/')
        ->and(OpeningHour::where('weekday', 1)->value('is_closed'))->toBeTrue()
        ->and(substr(OpeningHour::where('weekday', 5)->value('closes_at'), 0, 5))->toBe('02:00')
        ->and(AuditLog::where('action', 'establishment.updated')->exists())->toBeTrue();
});

it('serve o logo só do próprio estabelecimento', function () {
    $owner = createTenantUser($this->a);
    activateTenant($this->a);

    Livewire::actingAs($owner)->test(EditEstablishment::class)
        ->set('logo', UploadedFile::fake()->image('logo.png'))
        ->call('save')->assertHasNoErrors();

    app(App\Tenancy\TenantContext::class)->reset();

    $this->get(tenantUrl($this->a, '/logo'))->assertOk();
    // B nunca recebe logo nos testes: o arquivo de A não pode aparecer lá.
    $this->get(tenantUrl($this->b, '/logo'))->assertNotFound();
});

it('valida CNPJ, CEP e logo', function () {
    $owner = createTenantUser($this->a);
    activateTenant($this->a);

    Livewire::actingAs($owner)->test(EditEstablishment::class)
        ->set('form.document', '123')
        ->set('form.zip_code', 'abc')
        ->set('logo', UploadedFile::fake()->create('virus.exe', 10))
        ->call('save')
        ->assertHasErrors(['form.document', 'form.zip_code', 'logo']);
});

it('salva configurações com valores em centavos', function () {
    $owner = createTenantUser($this->a);
    activateTenant($this->a);

    Livewire::actingAs($owner)->test(EditSettings::class)
        ->set('welcome_message', 'Peça já!')
        ->set('min_order', '25,50')
        ->set('delivery_fee', '7')
        ->set('accepts_pickup', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::read('min_order_cents'))->toBe(2550)
        ->and(Setting::read('delivery_fee_cents'))->toBe(700)
        ->and(Setting::read('accepts_pickup'))->toBeFalse()
        ->and(Setting::read('welcome_message'))->toBe('Peça já!');

    app(App\Tenancy\TenantContext::class)->reset();
    $this->get(tenantUrl($this->a, '/cardapio'))->assertSee('Peça já!');
    $this->get(tenantUrl($this->b, '/cardapio'))->assertDontSee('Peça já!');
});

it('não deixa o caixa salvar dados do estabelecimento nem por ação Livewire direta', function () {
    $caixa = createTenantUser($this->a, ['caixa']);
    activateTenant($this->a);

    Livewire::actingAs($caixa)->test(EditSettings::class)->call('save')->assertForbidden();
});
