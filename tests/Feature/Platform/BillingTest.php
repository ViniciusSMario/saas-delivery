<?php

use App\Livewire\Platform\Billing\Index as BillingIndex;
use App\Livewire\Platform\Tenants\Show;
use App\Livewire\Tenant\Subscription;
use App\Modules\Access\Actions\SaveUser;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\User;
use App\Modules\Tables\Models\DiningTable;
use App\Platform\Billing\AsaasGateway;
use App\Platform\Billing\BillingGateway;
use App\Platform\Billing\Subscriptions;
use App\Platform\Models\Invoice;
use App\Platform\Models\Plan;
use App\Platform\Notifications\TenantNotice;
use App\Tenancy\Enums\TenantStatus;
use App\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function plan(string $slug): Plan
{
    return Plan::where('slug', $slug)->firstOrFail();
}

/** Estabelecimento em teste grátis no plano indicado, com um dono. */
function trialTenant(string $plan = 'essencial'): Tenant
{
    $tenant = provisionTenant('bill');
    app(Subscriptions::class)->startTrial($tenant, plan($plan), 'dono-'.$tenant->slug.'@teste.com');

    return $tenant->refresh();
}

function signupData(array $overrides = []): array
{
    return $overrides + [
        'name' => 'Pizzaria do Teste',
        'slug' => 'pizzaria-'.Str::lower(Str::random(6)),
        'owner_name' => 'Joana Dona',
        'email' => Str::lower(Str::random(8)).'@restaurante.com',
        'phone' => '11988887777',
        'password' => 'senha-forte-123',
        'password_confirmation' => 'senha-forte-123',
        'plan' => 'profissional',
        'terms' => '1',
    ];
}

beforeEach(fn () => RateLimiter::clear('signup:127.0.0.1'));

it('mostra o site com os planos e o formulário de cadastro', function () {
    $this->get('http://localhost/')->assertOk()->assertSee('Essencial')->assertSee('R$ 149,90')->assertSee('Teste grátis');
    $this->get('http://localhost/cadastro?plano=completo')->assertOk()->assertSee('Crie sua conta');
});

it('cria o estabelecimento pelo cadastro self-service, com teste grátis e dono com senha', function () {
    Notification::fake();
    $data = signupData();

    $this->post('http://localhost/cadastro', $data)->assertRedirect('http://localhost/cadastro/pronto');

    $tenant = Tenant::where('slug', $data['slug'])->firstOrFail();
    expect($tenant->status)->toBe(TenantStatus::Active)
        ->and($tenant->subscription_status)->toBe('trialing')
        ->and($tenant->plan->slug)->toBe('profissional')
        ->and($tenant->trial_ends_at->isSameDay(now()->addDays(14)))->toBeTrue()
        ->and($tenant->billing_email)->toBe($data['email']);

    $owner = inTenant($tenant, fn () => User::with('roles')->where('email', $data['email'])->firstOrFail());
    expect($owner->isOwner())->toBeTrue()->and(Hash::check('senha-forte-123', $owner->password))->toBeTrue();
    Notification::assertSentOnDemand(TenantNotice::class);

    $this->get('http://localhost/cadastro/pronto')->assertOk()->assertSee($data['slug'].'.localhost');
});

it('recusa endereço em uso ou reservado, robôs e excesso de tentativas', function () {
    $this->getJson('http://localhost/cadastro/disponivel?slug=Teste 1')->assertJson(['slug' => 'teste-1', 'available' => true]);
    $this->getJson('http://localhost/cadastro/disponivel?slug=teste1')->assertJson(['available' => false]);
    $this->getJson('http://localhost/cadastro/disponivel?slug=admin')->assertJson(['available' => false]);

    $this->post('http://localhost/cadastro', signupData(['slug' => 'teste1']))->assertSessionHasErrors('slug');
    $this->post('http://localhost/cadastro', signupData(['slug' => 'Nome Invalido']))->assertSessionHasErrors('slug');
    $this->post('http://localhost/cadastro', signupData(['terms' => null]))->assertSessionHasErrors('terms');
    $this->post('http://localhost/cadastro', signupData(['website' => 'http://spam']))->assertStatus(422);

    config(['platform.signup_per_hour' => 1]);
    Notification::fake();
    $this->post('http://localhost/cadastro', signupData())->assertRedirect();
    $this->post('http://localhost/cadastro', signupData())->assertSessionHasErrors('name');
});

it('aplica os limites e recursos do plano', function () {
    $tenant = trialTenant('essencial'); // 3 usuários, 10 mesas, sem estoque/financeiro
    $owner = createTenantUser($tenant, ['dono']);
    createTenantUser($tenant, ['caixa']);
    createTenantUser($tenant, ['garcom']);

    inTenant($tenant, function () {
        $role = Role::where('slug', 'cozinha')->value('id');
        expect(fn () => app(SaveUser::class)->handle(null, ['name' => 'Quarto', 'email' => 'quarto@teste.com'], [$role]))
            ->toThrow(ValidationException::class, 'permite até 3 usuários');
    });

    $this->actingAs($owner)->get(tenantUrl($tenant, '/estoque'))->assertForbidden();
    $this->actingAs($owner)->get(tenantUrl($tenant, '/financeiro'))->assertForbidden();
    skipOnboarding($tenant);
    $this->actingAs($owner)->get(tenantUrl($tenant, '/'))->assertSee('Insumos')->assertSee('Teste grátis');

    activateTenant($tenant);
    $this->actingAs($owner);
    Livewire::test(\App\Livewire\Tenant\Tables\Manage::class)
        ->set('range', '1-11')->call('createRange')->assertHasErrors('range')
        ->set('range', '1-10')->call('createRange')->assertHasNoErrors();
    expect(DiningTable::count())->toBe(10);

    // Plano maior libera.
    app(Subscriptions::class)->setPlan($tenant, plan('profissional'), 'teste');
    app(\App\Tenancy\TenantContext::class)->reset();
    $this->actingAs($owner)->get(tenantUrl($tenant, '/estoque'))->assertOk();
});

it('assina durante o teste e libera o acesso ao pagar a fatura', function () {
    Notification::fake();
    $tenant = trialTenant('essencial');
    $owner = createTenantUser($tenant, ['dono']);

    activateTenant($tenant);
    $this->actingAs($owner);
    Livewire::test(Subscription::class)->assertSee('Teste grátis')->call('subscribe', 'profissional')->assertHasNoErrors();

    $tenant->refresh();
    $invoice = Invoice::where('tenant_id', $tenant->id)->sole();
    expect($tenant->subscription_status)->toBe('trialing')
        ->and($tenant->plan->slug)->toBe('profissional')
        ->and($invoice->status)->toBe('pending')
        ->and($invoice->amount_cents)->toBe(14990)
        ->and($invoice->due_date->isSameDay($tenant->trial_ends_at))->toBeTrue();

    // Página da fatura de teste e "pagamento".
    $this->get($invoice->invoice_url)->assertOk()->assertSee('Simular pagamento');
    $this->post(URL::signedRoute('billing.fake.pay', ['charge' => $invoice->gateway_id]))->assertRedirect();

    $tenant->refresh();
    expect($invoice->refresh()->status)->toBe('paid')
        ->and($tenant->subscription_status)->toBe('active')
        ->and($tenant->current_period_ends_at->isSameDay($invoice->due_date->copy()->addMonthNoOverflow()))->toBeTrue();
});

it('não deixa trocar para um plano menor que o uso atual', function () {
    $tenant = trialTenant('profissional');
    $owner = createTenantUser($tenant, ['dono']);
    foreach (range(1, 3) as $i) {
        createTenantUser($tenant, ['garcom']);
    }

    activateTenant($tenant);
    $this->actingAs($owner);
    Livewire::test(Subscription::class)->call('subscribe', 'essencial')->assertHasErrors('plan');
    expect($tenant->refresh()->plan->slug)->toBe('profissional');
});

it('só o dono acessa a assinatura', function () {
    $tenant = trialTenant();
    $this->actingAs(createTenantUser($tenant, ['gerente']))->get(tenantUrl($tenant, '/assinatura'))->assertForbidden();
    $this->flushSession();
    $this->actingAs(createTenantUser($tenant, ['dono']))->get(tenantUrl($tenant, '/assinatura'))->assertOk()->assertSee('Escolha seu plano');
});

it('avisa o fim do teste, marca inadimplente, suspende depois da carência e reativa ao pagar', function () {
    Notification::fake();
    $tenant = trialTenant('essencial');
    createTenantUser($tenant, ['dono'], ['email' => 'dono-ciclo@teste.com']);
    $subscriptions = app(Subscriptions::class);

    // 12 dias depois: lembrete, uma vez só.
    $this->travel(12)->days();
    $this->artisan('billing:check')->assertSuccessful();
    $this->artisan('billing:check')->assertSuccessful();
    expect($tenant->events()->where('type', 'trial_reminder_sent')->count())->toBe(1);

    // Fim do teste sem plano: inadimplente, ainda com acesso.
    $this->travel(3)->days();
    $subscriptions->check();
    expect($tenant->refresh()->subscription_status)->toBe('past_due')->and($tenant->status)->toBe(TenantStatus::Active);

    // Passou a carência: suspenso, com o motivo "billing".
    $this->travel(8)->days();
    $subscriptions->check();
    $tenant->refresh();
    expect($tenant->status)->toBe(TenantStatus::Suspended)->and($tenant->suspension_reason)->toBe('billing');
    $this->get(tenantUrl($tenant))->assertForbidden()->assertSee('falta de pagamento');

    // Assinou (pela equipe/suporte) e pagou: volta sozinho.
    $subscriptions->subscribe($tenant, plan('essencial'), 'suporte');
    $invoice = Invoice::where('tenant_id', $tenant->id)->open()->sole();
    $this->get(tenantUrl($tenant))->assertSee('Pagar fatura');
    $subscriptions->syncCharge($tenant, new \App\Platform\Billing\Charge($invoice->gateway_id, 8990, \Carbon\CarbonImmutable::today(), 'paid'));

    $tenant->refresh();
    expect($tenant->status)->toBe(TenantStatus::Active)
        ->and($tenant->subscription_status)->toBe('active')
        ->and($tenant->suspension_reason)->toBeNull();
});

it('processa o webhook do Asaas uma vez só e recusa token inválido', function () {
    Notification::fake();
    config(['platform.billing.driver' => 'asaas', 'services.asaas.webhook_token' => 'segredo-webhook']);
    app()->instance(BillingGateway::class, new AsaasGateway);

    $tenant = trialTenant('essencial');
    $tenant->update(['billing_subscription_id' => 'sub_asaas_1']);
    $payload = ['id' => 'evt_1', 'event' => 'PAYMENT_RECEIVED', 'payment' => [
        'id' => 'pay_1', 'subscription' => 'sub_asaas_1', 'value' => 89.9, 'dueDate' => now()->toDateString(),
        'status' => 'RECEIVED', 'invoiceUrl' => 'https://sandbox.asaas.com/i/pay_1',
    ]];

    $this->postJson('http://localhost/webhooks/billing/asaas', $payload, ['asaas-access-token' => 'errado'])->assertUnauthorized();

    $this->postJson('http://localhost/webhooks/billing/asaas', $payload, ['asaas-access-token' => 'segredo-webhook'])->assertOk();
    $this->postJson('http://localhost/webhooks/billing/asaas', $payload, ['asaas-access-token' => 'segredo-webhook'])->assertOk();

    $invoice = Invoice::where('gateway_id', 'pay_1')->sole();
    expect($invoice->status)->toBe('paid')->and($invoice->amount_cents)->toBe(8990)
        ->and($tenant->refresh()->subscription_status)->toBe('active')
        ->and($tenant->events()->where('type', 'invoice_paid')->count())->toBe(1);
});

it('cria cliente e assinatura no Asaas', function () {
    config(['services.asaas.key' => 'chave-teste', 'services.asaas.url' => 'https://asaas.test/v3']);
    app()->instance(BillingGateway::class, new AsaasGateway);
    Http::fake([
        'asaas.test/v3/customers' => Http::response(['id' => 'cus_9']),
        'asaas.test/v3/subscriptions' => Http::response(['id' => 'sub_9']),
        'asaas.test/v3/subscriptions/sub_9/payments' => Http::response(['data' => [[
            'id' => 'pay_9', 'value' => 149.9, 'dueDate' => now()->addDays(14)->toDateString(), 'status' => 'PENDING',
            'invoiceUrl' => 'https://sandbox.asaas.com/i/pay_9', 'subscription' => 'sub_9',
        ]]]),
    ]);

    $tenant = trialTenant('essencial');
    $url = app(Subscriptions::class)->subscribe($tenant, plan('profissional'), 'teste');

    expect($url)->toBe('https://sandbox.asaas.com/i/pay_9')
        ->and($tenant->refresh()->billing_customer_id)->toBe('cus_9')
        ->and($tenant->billing_subscription_id)->toBe('sub_9');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/subscriptions')
        && $request['billingType'] === 'UNDEFINED' && $request['value'] == 149.9 && $request->hasHeader('access_token', 'chave-teste'));
});

it('mostra as assinaturas e ajusta plano e teste pelo admin', function () {
    $tenant = trialTenant('essencial');
    $this->actingAs(createPlatformUser(), 'platform');

    Livewire::test(BillingIndex::class)->assertSee($tenant->name)->assertSee('Receita recorrente');

    Livewire::test(Show::class, ['tenant' => $tenant])->assertSee('Assinatura')
        ->set('planId', plan('completo')->id)->call('setPlan')
        ->call('extendTrial');

    $tenant->refresh();
    expect($tenant->plan->slug)->toBe('completo')
        ->and($tenant->trial_ends_at->isSameDay(now()->addDays(21)))->toBeTrue();

    Livewire::test(Show::class, ['tenant' => $tenant])->call('makeCourtesy');
    expect($tenant->refresh()->subscription_status)->toBeNull();
});
