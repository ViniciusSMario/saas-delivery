<?php

use App\Livewire\Tenant\Customers\Index as CustomersIndex;
use App\Livewire\Tenant\Menu\Products\Index as ProductsIndex;
use App\Livewire\Tenant\Orders\Board;
use App\Livewire\Tenant\Orders\Index as OrdersIndex;
use App\Livewire\Tenant\Promotions\Coupons;
use App\Livewire\Tenant\Stock\Index as StockIndex;
use App\Livewire\Tenant\Stock\Show as StockShow;
use App\Modules\Customers\Models\Customer;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\Unit;
use App\Support\PanelMenu;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 15:00', 'America/Sao_Paulo'));
    $this->tenant = provisionTenant('ux');
    inTenant($this->tenant, fn () => OpeningHour::query()->update(['is_closed' => false, 'opens_at' => '00:00', 'closes_at' => '23:59']));
    $this->menu = orderMenu($this->tenant);
    skipOnboarding($this->tenant);
});

it('a busca rápida acha pedido, cliente, produto e insumo, só no que a pessoa pode ver', function () {
    $order = placeOnline($this->tenant, onlineOrderInput($this->menu, ['customer' => ['name' => 'Joana Prado', 'phone' => '(11) 97654-3210']]));
    inTenant($this->tenant, function () {
        StockItem::create(['name' => 'Mussarela ralada', 'unit_id' => Unit::where('code', 'kg')->value('id')]);
        $this->menu['pizza']->update(['name' => 'Pizza Mussarela', 'code' => 'PZ1']);
    });
    $dono = createTenantUser($this->tenant, ['dono']);

    $search = fn (string $q) => collect($this->get(tenantUrl($this->tenant, '/busca?q='.urlencode($q)))->assertOk()->json('results'));

    $this->actingAs($dono);
    expect($search('#'.$order->number)->firstWhere('group', 'Pedidos')['title'])->toBe("#{$order->number} · Joana Prado")
        ->and($search('joana')->pluck('group')->unique()->values()->all())->toBe(['Pedidos', 'Clientes'])
        ->and($search('97654')->firstWhere('group', 'Clientes')['subtitle'])->toBe('(11) 97654-3210')
        ->and($search('mussa')->pluck('title', 'group')->all())->toBe(['Produtos' => 'Pizza Mussarela', 'Insumos' => 'Mussarela ralada'])
        ->and($search('pz1')->firstWhere('group', 'Produtos')['url'])->toEndWith('/produtos/'.$this->menu['pizza']->id)
        ->and($search('j')->all())->toBe([]);   // uma letra só: nada (as telas o navegador filtra)
});

it('na busca rápida, a cozinha só acha pedidos', function () {
    placeOnline($this->tenant, onlineOrderInput($this->menu, ['customer' => ['name' => 'Joana Prado', 'phone' => '(11) 97654-3210']]));
    inTenant($this->tenant, fn () => $this->menu['pizza']->update(['name' => 'Pizza Joana']));
    $this->actingAs(createTenantUser($this->tenant, ['cozinha']));

    $results = collect($this->get(tenantUrl($this->tenant, '/busca?q=joana'))->assertOk()->json('results'));
    expect($results->pluck('group')->unique()->values()->all())->toBe(['Pedidos']);
});

it('a busca rápida exige login e lista só as telas permitidas', function () {
    $this->get(tenantUrl($this->tenant, '/busca?q=pizza'))->assertRedirect();

    $caixa = createTenantUser($this->tenant, ['caixa']);
    $pages = inTenant($this->tenant, fn () => collect(PanelMenu::pagesFor($caixa))->pluck('label'));
    expect($pages)->toContain('PDV', 'Caixa', 'Clientes')->not->toContain('Usuários', 'Produtos', 'Configurações');

    $actions = inTenant($this->tenant, fn () => collect(PanelMenu::actionsFor($caixa))->pluck('label'));
    expect($actions)->toContain('Nova venda no balcão', 'Ver o cardápio online')->not->toContain('Novo produto');

    // A janela vem no painel, com as telas da pessoa.
    $this->actingAs($caixa)->get(tenantUrl($this->tenant, '/'))
        ->assertOk()->assertSee('x-data="commandPalette"', false)->assertSee('Nova venda no balcão')->assertDontSee('Perfis e permissões');
});

it('avisos rápidos: salvar sem recarregar, esgotar com desfazer e mensagem depois do redirecionamento', function () {
    activateTenant($this->tenant);
    $dono = createTenantUser($this->tenant, ['dono']);
    $pizza = $this->menu['pizza'];

    // Esgotado com "Desfazer": o botão chama o mesmo método de volta.
    $page = Livewire::actingAs($dono)->test(ProductsIndex::class)->call('toggleAvailability', $pizza->id);
    $page->assertDispatched('toast', fn ($name, $params) => $params['message'] === "{$pizza->name} marcado como esgotado."
        && $params['undo']['method'] === 'toggleAvailability' && $params['undo']['params'] === [$pizza->id]);
    expect(Product::find($pizza->id)->is_available)->toBeFalse();
    $page->call('toggleAvailability', $pizza->id)->assertDispatched('toast', fn ($name, $params) => $params['message'] === "{$pizza->name} voltou ao cardápio." && $params['tone'] === 'info');
    expect(Product::find($pizza->id)->is_available)->toBeTrue();

    // Telas que salvam sem sair do lugar: antes a mensagem só aparecia na página seguinte.
    $item = StockItem::create(['name' => 'Farinha', 'unit_id' => Unit::where('code', 'kg')->value('id')]);
    Livewire::actingAs($dono)->test(StockShow::class, ['item' => $item])->set('name', 'Farinha 00')->call('save')
        ->assertDispatched('toast', message: 'Insumo salvo.');
    expect(session('status'))->toBeNull();

    Livewire::actingAs($dono)->test(Coupons::class)->call('create')->set('code', 'BEMVINDO')->set('percent', '10')->call('save')
        ->assertDispatched('toast', message: 'Cupom BEMVINDO salvo.');

    // Painel de pedidos: avançar avisa; pedido que outro atendente mexeu vira aviso de erro.
    $order = placeOnline($this->tenant, onlineOrderInput($this->menu));
    activateTenant($this->tenant);
    $board = Livewire::actingAs($dono)->test(Board::class)->call('advance', $order->id)
        ->assertDispatched('toast', message: "Pedido #{$order->number}: ".OrderStatus::Preparing->label().'.');
    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);

    // Mensagem guardada antes de redirecionar aparece na tela seguinte como aviso.
    $this->actingAs($dono)->withSession(['status' => 'Produto salvo.'])->get(tenantUrl($this->tenant, '/produtos'))
        ->assertOk()->assertSee('data-flash-toast="success"', false)->assertSee('Produto salvo.');
});

it('as telas de operação têm atalhos de teclado', function () {
    $dono = createTenantUser($this->tenant, ['dono']);
    $this->actingAs($dono);

    $this->get(tenantUrl($this->tenant, '/balcao'))->assertOk()
        ->assertSee('data-hotkey="F9 ctrl+enter"', false)
        ->assertSee('data-hotkey="/ F3"', false)
        ->assertSee('data-hotkey="F2"', false)
        ->assertSee('data-hotkey="alt+3"', false);

    $this->get(tenantUrl($this->tenant, '/pedidos'))->assertOk()
        ->assertSee('data-hotkey-help="enter"', false)
        ->assertSee('data-hotkey="n"', false);
});

it('as listas vazias dizem o que fazer, e a busca sem resultado oferece limpar', function () {
    $empty = provisionTenant('ux');
    skipOnboarding($empty);
    activateTenant($empty);
    $dono = createTenantUser($empty, ['dono']);

    // Sem categoria ainda: o primeiro passo é criar uma (ou importar a planilha).
    Livewire::actingAs($dono)->test(ProductsIndex::class)
        ->assertSee('Seu cardápio ainda está vazio')->assertSee('Criar categoria')->assertSee('Importar planilha')->assertSee('Usar um modelo pronto')
        ->set('search', 'xyz')->assertSee('Nenhum produto encontrado')->assertSee('Limpar filtros')
        ->call('clearFilters')->assertSet('search', '')->assertSee('Seu cardápio ainda está vazio');

    Livewire::actingAs($dono)->test(CustomersIndex::class)
        ->assertSee('Nenhum cliente ainda')->assertSee('Importar clientes')
        ->set('search', 'Zé')->assertSee('Nenhum cliente encontrado')->assertSee('Limpar busca');

    Livewire::actingAs($dono)->test(StockIndex::class)->assertSee('Nenhum insumo ainda')->assertSee('Importar insumos')
        ->set('onlyLow', true)->assertSee('Nenhum insumo abaixo do mínimo')->call('clearFilters')->assertSet('onlyLow', false);

    Livewire::actingAs($dono)->test(OrdersIndex::class)->assertSee('Nenhum pedido neste dia')->assertSee('Venda no balcão')->assertDontSee('Ver hoje')
        ->set('date', '2026-01-05')->assertSee('Ver hoje')
        ->set('status', 'ready')->assertSee('Nenhum pedido encontrado')->call('clearFilters')->assertSet('status', '');
});

it('as tabelas principais viram cartões no celular', function () {
    $this->actingAs(createTenantUser($this->tenant, ['dono']));

    foreach (['/produtos', '/clientes', '/estoque', '/pedidos/historico', '/entregas/bairros', '/cupons', '/financeiro', '/usuarios'] as $path) {
        $this->get(tenantUrl($this->tenant, $path))->assertOk()->assertSee('table table-cards', false);
    }
});
