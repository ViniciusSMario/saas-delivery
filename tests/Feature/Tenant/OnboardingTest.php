<?php

use App\Livewire\Tenant\Dashboard;
use App\Livewire\Tenant\Onboarding\Wizard;
use App\Modules\Access\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Establishment\Models\Establishment;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Establishment\Onboarding;
use App\Modules\Menu\Import\MenuImporter;
use App\Modules\Menu\Import\MenuTemplates;
use App\Modules\Imports\Models\DataImport;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PaymentMethod;
use App\Modules\Tables\Models\DiningTable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/** Restaurante recém-cadastrado: sem cardápio, só o dono. */
function freshRestaurant(): array
{
    $tenant = provisionTenant('novo');
    activateTenant($tenant);
    $dono = createTenantUser($tenant, ['dono']);
    activateTenant($tenant);

    return compact('tenant', 'dono');
}

function stepsDone(): array
{
    return collect(app(Onboarding::class)->steps())->filter(fn ($s) => $s['done'] || $s['skipped'])->pluck('key')->all();
}

it('o primeiro acesso abre o assistente; quem já usa o sistema não é levado para lá', function () {
    ['tenant' => $tenant, 'dono' => $dono] = freshRestaurant();

    $this->actingAs($dono);
    $this->get(tenantUrl($tenant, '/'))->assertRedirect(tenantUrl($tenant, '/primeiros-passos'));
    $this->get(tenantUrl($tenant, '/primeiros-passos'))->assertOk()->assertSee('Primeiros passos')->assertSee('Nome do restaurante');

    // Restaurante com cardápio (já em uso): Início normal, com o checklist.
    ['tenant' => $outro] = salon();
    $gerente = createTenantUser($outro, ['gerente']);
    $this->actingAs($gerente);
    Livewire::actingAs($gerente)->test(Dashboard::class)->assertNoRedirect()->assertSee('Primeiros passos:');
});

it('o assistente salva cada etapa de verdade e o checklist acompanha', function () {
    Notification::fake();
    ['dono' => $dono] = freshRestaurant();
    $this->actingAs($dono);

    $wizard = Livewire::actingAs($dono)->test(Wizard::class)->assertSet('step', 'store');

    // Loja: telefone e endereço obrigatórios.
    $wizard->set('store.phone', '')->call('saveStore')->assertHasErrors(['store.phone', 'store.street', 'store.city'])
        ->set('store.trade_name', 'Pizzaria do Zé')->set('store.phone', '(11) 98888-7777')->set('store.zip_code', '01310-100')
        ->set('store.street', 'Avenida Paulista')->set('store.number', '1000')->set('store.city', 'São Paulo')->set('store.state', 'sp')
        ->call('saveStore')->assertHasNoErrors()->assertSet('step', 'hours');
    expect(Establishment::current()->only(['trade_name', 'phone', 'street', 'state']))->toBe(['trade_name' => 'Pizzaria do Zé', 'phone' => '(11) 98888-7777', 'street' => 'Avenida Paulista', 'state' => 'SP']);

    // Horário: atalho "terça a domingo, 18h às 23h".
    $wizard->call('applyPreset', 'tue_sun')->call('saveHours')->assertHasNoErrors()->assertSet('step', 'sales');
    expect(OpeningHour::where('weekday', 1)->value('is_closed'))->toBeTrue()
        ->and(substr(OpeningHour::where('weekday', 5)->value('opens_at'), 0, 5))->toBe('18:00');

    // Entrega e pagamento.
    $pix = PaymentMethod::where('code', 'pix')->value('id');
    $cash = PaymentMethod::where('code', 'cash')->value('id');
    $wizard->set('acceptsPickup', false)->set('deliveryFee', '7,50')->set('minOrder', '30')->set('etaDelivery', 50)
        ->set('payments', [$pix => true, $cash => false])
        ->call('saveSales')->assertHasNoErrors()->assertSet('step', 'menu');
    expect([Setting::read('accepts_pickup'), Setting::read('delivery_fee_cents'), Setting::read('min_order_cents')])->toBe([false, 750, 3000])
        ->and(PaymentMethod::active()->pluck('code')->all())->toBe(['pix']);

    // Cardápio: sem produto não avança; o modelo pronto cria tudo (e pode ser desfeito).
    $wizard->call('confirmMenu')->assertHasErrors('menu')
        ->call('useTemplate', 'pizzaria')->assertHasNoErrors()->assertSee('Modelo Pizzaria aplicado');
    expect(Product::count())->toBe(10)
        ->and(Product::where('name', 'Margherita')->first()->addonGroups->pluck('name')->all())->toBe(['Borda', 'Adicionais da pizza'])
        ->and(DataImport::sole()->file_name)->toBe('Modelo: Pizzaria');
    $wizard->call('confirmMenu')->assertSet('step', 'tables');

    // Mesas e equipe.
    $wizard->set('tableCount', 6)->call('createTables')->assertHasNoErrors()->assertSet('step', 'team');
    expect(DiningTable::orderBy('position')->pluck('name')->all())->toBe(['1', '2', '3', '4', '5', '6']);
    $wizard->set('inviteName', 'Carla')->set('inviteEmail', 'carla@pizzaria.test')->set('inviteRole', 'caixa')->call('invite')
        ->assertHasNoErrors()->assertSee('Convite enviado para carla@pizzaria.test')
        ->call('confirmTeam')->assertSet('step', 'test');
    expect(User::where('email', 'carla@pizzaria.test')->first()->roles->pluck('slug')->all())->toBe(['caixa']);

    // Pedido de teste: entra como pedido online (alarme), sem cliente, e é cancelado no fim.
    OpeningHour::query()->update(['is_closed' => true]); // fora do horário também funciona
    $wizard->call('startTest')->assertHasNoErrors()->assertSee('Painel de pedidos');
    $order = Order::sole();
    expect($order->source)->toBe(OrderSource::Online)->and($order->customer_name)->toBe('Pedido de teste')
        ->and($order->customer_id)->toBeNull()->and(Customer::count())->toBe(0);
    $wizard->call('finishTest')->assertSet('step', 'share');
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);

    // Divulgar e concluir.
    $wizard->assertSee('/cardapio')->assertSee('<svg', false)
        ->call('finish')->assertRedirect(route('dashboard'));
    expect(stepsDone())->toBe(array_keys(Onboarding::STEPS))
        ->and(app(Onboarding::class)->visibleFor($dono))->toBeFalse();
    Livewire::actingAs($dono)->test(Dashboard::class)->assertDontSee('Primeiros passos:');
});

it('etapas opcionais podem ser puladas e o checklist pode ser escondido', function () {
    ['dono' => $dono] = freshRestaurant();
    $this->actingAs($dono);

    Livewire::actingAs($dono)->test(Wizard::class, ['step' => 'tables'])
        ->call('skip', 'tables')->assertSet('step', 'team')
        ->call('skip', 'team');
    expect(stepsDone())->toBe(['tables', 'team']);

    // Etapa obrigatória não pula.
    expect(fn () => app(Onboarding::class)->mark('menu', skipped: true))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    Livewire::actingAs($dono)->test(Dashboard::class)->assertSee('Primeiros passos: 2 de 8')
        ->call('dismissOnboarding')->assertDontSee('Primeiros passos:');
    expect(app(Onboarding::class)->shouldStartFor($dono))->toBeFalse();
});

it('os cinco modelos prontos importam sem erro e podem ser desfeitos', function () {
    ['dono' => $dono] = freshRestaurant();

    foreach (array_keys(MenuTemplates::segments()) as $segment) {
        $import = app(MenuTemplates::class)->apply($segment, $dono);
        expect($import->summary['products_created'])->toBeGreaterThan(5);
        app(MenuImporter::class)->undo($import, $dono);
        expect(Product::withTrashed()->count())->toBe(0);
    }
});

it('só quem cuida do estabelecimento abre o assistente', function () {
    ['tenant' => $tenant] = freshRestaurant();
    $caixa = createTenantUser($tenant, ['caixa']);

    $this->actingAs($caixa);
    $this->get(tenantUrl($tenant, '/primeiros-passos'))->assertForbidden();
    $this->get(tenantUrl($tenant, '/'))->assertOk(); // sem redirecionar para o assistente
});
