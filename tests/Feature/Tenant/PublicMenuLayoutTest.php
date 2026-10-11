<?php

use App\Livewire\Tenant\Establishment\EditEstablishment;
use App\Modules\Establishment\Models\Establishment;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\OrderSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('o cardápio tem foto de capa, busca e carrinho no topo e mostra os mais pedidos', function () {
    ['tenant' => $tenant, 'burger' => $burger, 'soda' => $soda] = salon();
    $gerente = createTenantUser($tenant, ['gerente']);
    $menu = fn () => asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, '/cardapio'))->assertOk());

    // Sem capa e sem vendas: degradê da marca e sem vitrine.
    $menu()->assertDontSee('/capa?v=', false)->assertDontSee('Mais pedido')
        ->assertSee('Buscar no cardápio')->assertSee('~45 min')->assertSee('~20 min');

    // A gerência sobe a foto de capa em Estabelecimento.
    Livewire::actingAs($gerente)->test(EditEstablishment::class)
        ->set('form.trade_name', 'Casa do Teste')
        ->set('cover', UploadedFile::fake()->image('capa.jpg', 1200, 400))
        ->call('save')->assertHasNoErrors();
    $cover = Establishment::current()->cover_path;
    expect($cover)->not->toBeNull()->and(Storage::disk('tenant')->exists($cover))->toBeTrue();
    $menu()->assertSee('/capa?v=', false);
    asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, '/cardapio/capa')))->assertOk();

    // 3 hambúrgueres vendidos no mês: vira "Mais pedidos" na vitrine e ganha o selo.
    app(PlaceOrder::class)->handle([
        'lines' => [line($burger, 3)], 'fulfillment' => 'pickup', 'payment_method' => 'cash',
        'customer' => ['name' => 'Rita', 'phone' => '11977775555'],
    ], OrderSource::Online);
    cache()->forget('menu-popular:'.$tenant->getKey());
    $menu()->assertSee('Mais pedidos')->assertSee('Mais pedido');

    // Produto marcado como destaque pelo restaurante tem prioridade na vitrine.
    $soda->update(['is_featured' => true]);
    $menu()->assertSee('Destaques')->assertDontSee('Mais pedidos');

    // Remover a capa apaga o arquivo e volta ao degradê.
    Livewire::actingAs($gerente)->test(EditEstablishment::class)->call('removeCover');
    expect(Establishment::current()->cover_path)->toBeNull()->and(Storage::disk('tenant')->exists($cover))->toBeFalse();
    asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, '/cardapio/capa')))->assertNotFound();
});
