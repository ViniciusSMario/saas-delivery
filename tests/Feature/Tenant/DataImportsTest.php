<?php

use App\Livewire\Tenant\Imports\Index as ImportsPage;
use App\Modules\Customers\Models\Customer;
use App\Modules\Delivery\Models\DeliveryZone;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Imports\Models\DataImport;
use App\Modules\Imports\TabularImporter;
use App\Modules\Menu\Models\AddonGroup;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Stock\Models\Recipe;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockMovement;
use App\Modules\Stock\Models\Unit;
use App\Modules\Stock\StockLedger;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

/** CSV com ";" (como o Excel brasileiro salva). */
function dataCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'dados').'.csv';
    file_put_contents($path, implode("\r\n", array_map(fn ($row) => implode(';', $row), $rows))."\r\n");

    return $path;
}

/** Planeja e grava como a tela faz; devolve [plano, importação ou null]. */
function importData(string $slug, array $rows, string $mode = 'update'): array
{
    $importer = app(TabularImporter::class);
    $type = TabularImporter::type($slug);
    $plan = $importer->plan($type, dataCsv($rows), 'csv', $mode);

    return [$plan, $plan->hasErrors() ? null : $importer->apply($type, $plan, $mode, "{$slug}.csv", null)];
}

// ---------- Clientes ----------

it('importa clientes pelo telefone, sem duplicar, com endereço e desfazer', function () {
    salon();
    $ana = Customer::create(['name' => 'Ana', 'phone' => '11988887777']);
    $ana->addresses()->create(['street' => 'Rua A', 'number' => '10', 'district' => 'Centro']);

    $head = ['Nome', 'Celular', 'E-mail', 'Obs', 'CEP', 'Endereço', 'Número', 'Complemento', 'Bairro', 'Cidade', 'Referência'];
    $rows = [
        $head,
        ['Ana Lima', '(11) 98888-7777', '', 'Sem cebola', '', 'Rua B', '20', '', 'Jardim', 'São Paulo', ''],   // existe: nome, obs e endereço novo
        ['Bruno Costa', '+55 11 97777-6666', 'bruno@email.com', '', '01310-100', 'Av. Paulista', '1000', 'Apto 5', 'Bela Vista', 'São Paulo', ''],
        ['Carla', '11966665555', '', '', '', '', '', '', '', '', ''],
    ];

    [$skip] = importData('clientes', $rows, 'skip');
    expect(collect($skip->rows)->pluck('action')->all())->toBe(['skip', 'create', 'create']);
    DataImport::query()->delete();
    Customer::whereNot('id', $ana->id)->forceDelete();

    [$plan, $import] = importData('clientes', $rows);
    expect($plan->rows[0]['changes'])->toBe(['nome: Ana → Ana Lima', 'observações', 'endereço novo'])
        ->and(Customer::count())->toBe(3)
        ->and($ana->fresh()->only(['name', 'notes']))->toBe(['name' => 'Ana Lima', 'notes' => 'Sem cebola'])
        ->and($ana->addresses()->count())->toBe(2)
        ->and(Customer::where('phone', '11977776666')->first()->addresses()->first()->only(['zip_code', 'complement']))->toBe(['zip_code' => '01310-100', 'complement' => 'Apto 5']);

    // Erros por linha: telefone inválido, endereço pela metade, telefone repetido.
    [$bad, $none] = importData('clientes', [
        ['Nome', 'Telefone', 'Rua', 'Número', 'Bairro'],
        ['Davi', '1234', '', '', ''],
        ['Elisa', '11955554444', 'Rua C', '', ''],
        ['Fabi', '11944443333', '', '', ''],
        ['Fabi de novo', '(11) 94444-3333', '', '', ''],
    ]);
    expect($none)->toBeNull()->and($bad->errors)->toBe([
        'Linha 2: telefone inválido ("1234"), use DDD + número.',
        'Linha 3: endereço incompleto: precisa de Rua, Número e Bairro.',
        'Linha 5: telefone repetido na linha 4.',
    ]);

    // Cliente que pediu depois da importação fica ao desfazer; os outros saem e a Ana volta a ser como era.
    app(PlaceOrder::class)->handle(['lines' => [line(Product::first())], 'fulfillment' => 'pickup', 'payment_method' => 'cash',
        'customer' => ['name' => 'Carla', 'phone' => '11966665555']], OrderSource::Online);
    app(TabularImporter::class)->undo($import, null);

    expect($ana->fresh()->only(['name', 'notes']))->toBe(['name' => 'Ana', 'notes' => null])
        ->and($ana->addresses()->count())->toBe(1)
        ->and(Customer::where('phone', '11977776666')->exists())->toBeFalse()
        ->and(Customer::where('phone', '11966665555')->exists())->toBeTrue();
});

// ---------- Bairros ----------

it('importa bairros com taxa, mínimo e tempo e avisa quando a entrega está com taxa única', function () {
    salon();
    DeliveryZone::create(['name' => 'Centro', 'fee_cents' => Money::parse('5')]);

    [$plan] = importData('bairros', [
        ['Bairro', 'Frete', 'Pedido mínimo', 'Tempo', 'Ativo'],
        ['CENTRO', '6,50', '', '30', ''],
        ['Jardim América', 'R$ 8,00', '40', '45', 'sim'],
        ['Vila Nova', '0', '', '', 'não'],
    ]);

    expect($plan->warnings[0])->toContain('taxa única')
        ->and($plan->rows[0]['changes'])->toBe(['taxa: R$ 5,00 → R$ 6,50', 'tempo: — → 30 min'])
        ->and(DeliveryZone::where('name', 'Centro')->first()->fee_cents->format())->toBe('R$ 6,50')
        ->and(DeliveryZone::where('name', 'Jardim América')->first()->only(['eta_minutes', 'is_active']))->toBe(['eta_minutes' => 45, 'is_active' => true])
        ->and(DeliveryZone::where('name', 'Jardim América')->first()->min_order_cents->format())->toBe('R$ 40,00')
        ->and(DeliveryZone::where('name', 'Vila Nova')->value('is_active'))->toBeFalse();

    Setting::write('delivery_fee_mode', 'zones');
    [$again] = importData('bairros', [['Bairro', 'Taxa'], ['Centro', 'abc'], ['Centro', '7']]);
    expect($again->warnings)->toBe([])->and($again->errors)->toBe(['Linha 2: taxa inválida ("abc"), use 5,00.']);
});

// ---------- Insumos ----------

it('importa insumos com saldo e custo pelo livro do estoque e desfaz voltando o saldo', function () {
    salon();
    $kg = Unit::where('code', 'kg')->first();
    $farinha = StockItem::create(['name' => 'Farinha de trigo', 'unit_id' => $kg->id]);
    app(StockLedger::class)->purchase($farinha, '10', Money::parse('42'), null, 'compra');

    [$plan, $import] = importData('insumos', [
        ['Insumo', 'Unidade', 'Mínimo', 'Estoque', 'Custo'],
        ['Muçarela', 'quilo', '2', '5,5', '38,90'],
        ['farinha de trigo', 'kg', '8', '7', ''],
        ['Refrigerante lata', 'unidade', '', '', ''],
    ]);

    $mussarela = StockItem::where('name', 'Muçarela')->first();
    expect($plan->errors)->toBe([])
        ->and($plan->rows[1]['changes'])->toBe(['mínimo: 0 → 8', 'saldo: 10 → 7 kg'])
        ->and((string) $mussarela->quantity)->toBe('5.500')
        ->and($mussarela->average_cost_cents)->toBe(3890)
        ->and(StockMovement::where('stock_item_id', $mussarela->id)->value('reason'))->toBe('Importação de planilha')
        ->and((string) $farinha->fresh()->quantity)->toBe('7.000')
        ->and(StockItem::where('name', 'Refrigerante lata')->first()->unit->code)->toBe('un');

    // Unidade não muda depois de ter movimento; unidade desconhecida é erro.
    [$bad] = importData('insumos', [['Insumo', 'Unidade'], ['Farinha de trigo', 'g'], ['Sal', 'pitada']]);
    expect($bad->errors)->toBe([
        'Linha 2: a unidade não pode mudar: o insumo já tem movimentação ou ficha técnica.',
        'Linha 3: unidade "pitada" não reconhecida (use un, kg, g, l ou ml).',
    ]);

    app(TabularImporter::class)->undo($import, null);
    expect(StockItem::where('name', 'Muçarela')->exists())->toBeFalse()
        ->and((string) $farinha->fresh()->quantity)->toBe('10.000')
        ->and((string) $farinha->fresh()->min_quantity)->toBe('0.000');
});

// ---------- Fichas técnicas ----------

it('importa fichas técnicas de produtos e adicionais, substituindo a ficha inteira', function () {
    ['burger' => $burger, 'soda' => $soda] = salon();
    $burger->update(['code' => 'H1']);
    $kg = Unit::where('code', 'kg')->first();
    $un = Unit::where('code', 'un')->first();
    $carne = StockItem::create(['name' => 'Carne moída', 'unit_id' => $kg->id]);
    $pao = StockItem::create(['name' => 'Pão', 'unit_id' => $un->id]);
    $bacon = StockItem::create(['name' => 'Bacon', 'unit_id' => $kg->id]);
    $lata = StockItem::create(['name' => 'Lata', 'unit_id' => $un->id]);
    $extras = AddonGroup::create(['name' => 'Extras', 'max_choices' => 3]);
    $extras->addons()->create(['name' => 'Bacon', 'price_cents' => Money::parse('5')]);
    $outro = AddonGroup::create(['name' => 'Outro grupo', 'max_choices' => 1]);
    $outro->addons()->create(['name' => 'Bacon', 'price_cents' => Money::parse('6')]);
    Recipe::create(['product_id' => $burger->id])->items()->create(['stock_item_id' => $pao->id, 'quantity' => '2', 'unit_id' => $un->id]);

    $rows = [
        ['Produto', 'Código', 'Adicional', 'Grupo', 'Ingrediente', 'Qtd', 'Unidade'],
        ['', 'h1', '', '', 'Carne moída', '150', 'g'],
        ['Hambúrguer', '', '', '', 'pão', '1', 'un'],
        ['Refrigerante', '', '', '', 'Lata', '1', 'un'],
        ['', '', 'Bacon', 'Extras', 'Bacon', '30', 'g'],
    ];

    [$plan, $import] = importData('fichas', $rows);
    expect($plan->errors)->toBe([])
        ->and(collect($plan->rows)->pluck('action')->all())->toBe(['update', 'create', 'create'])
        ->and(Recipe::where('product_id', $burger->id)->first()->items->map(fn ($i) => [$i->stockItem->name, (string) $i->quantity, $i->unit->code])->all())
        ->toBe([['Carne moída', '150.000', 'g'], ['Pão', '1.000', 'un']])
        ->and(Recipe::whereNotNull('addon_id')->first()->addon->group->name)->toBe('Extras');

    [$bad] = importData('fichas', [
        ['Produto', 'Adicional', 'Insumo', 'Quantidade', 'Unidade'],
        ['', 'Bacon', 'Bacon', '30', 'g'],
        ['Pizza', '', 'Bacon', '30', 'g'],
        ['Refrigerante', '', 'Gelo', '1', 'un'],
        ['Refrigerante', '', 'Lata', '1', 'kg'],
    ]);
    expect($bad->errors)->toBe([
        'Linha 2: o adicional "Bacon" existe em mais de um grupo: preencha o Grupo.',
        'Linha 3: produto "Pizza" não está no cardápio.',
        'Linha 4: o insumo "Gelo" não está no estoque (importe os insumos antes).',
        'Linha 5: unidade incompatível com o insumo (controlado em un).',
    ]);

    app(TabularImporter::class)->undo($import, null);
    expect(Recipe::where('product_id', $burger->id)->first()->items->pluck('stock_item_id')->all())->toBe([$pao->id])
        ->and(Recipe::where('product_id', $soda->id)->exists())->toBeFalse()
        ->and(Recipe::whereNotNull('addon_id')->exists())->toBeFalse();
});

// ---------- Tela ----------

it('a tela mostra só os tipos permitidos, confere antes de gravar e desfaz por tipo', function () {
    ['tenant' => $tenant] = salon();
    $caixa = createTenantUser($tenant, ['caixa']);

    $this->actingAs($caixa);
    $page = Livewire::actingAs($caixa)->test(ImportsPage::class)
        ->assertSet('type', 'clientes')->assertSee('Clientes')->assertDontSee('Insumos do estoque')->assertDontSee('Cardápio');

    $csv = dataCsv([['Nome', 'Telefone'], ['Gil', '11933332222'], ['Hana', '11922221111']]);
    $page->set('file', UploadedFile::fake()->createWithContent('clientes.csv', file_get_contents($csv)))
        ->assertHasNoErrors()->assertSee('Gil')->assertSee('Importar agora');
    expect(Customer::count())->toBe(0);

    $page->call('apply')->assertSee('Importação concluída.')->assertSee('2 clientes novos')->assertSee('Desfazer');
    expect(Customer::count())->toBe(2);

    // Desfazer pela tela.
    $page->call('undo', DataImport::ofKind('customers')->value('id'))->assertSee('Desfeita');
    expect(Customer::count())->toBe(0);

    // Caixa não acessa os tipos dos outros.
    Livewire::actingAs($caixa)->test(ImportsPage::class)->call('choose', 'insumos')->assertForbidden();
    $this->get(tenantUrl($tenant, '/importar-dados/insumos/modelo.xlsx'))->assertForbidden();
    $this->get(tenantUrl($tenant, '/importar-dados/clientes/modelo.xlsx'))->assertOk()->assertDownload('modelo-clientes.xlsx');
});

it('a exportação de cada tipo volta igual pela importação', function () {
    ['tenant' => $tenant] = salon();
    $gerente = createTenantUser($tenant, ['gerente']);
    Customer::create(['name' => 'Ana', 'phone' => '11988887777', 'notes' => 'VIP']);
    DeliveryZone::create(['name' => 'Centro', 'fee_cents' => Money::parse('5'), 'eta_minutes' => 30]);

    $this->actingAs($gerente);

    foreach (['clientes', 'bairros', 'insumos', 'fichas'] as $slug) {
        $response = $this->get(tenantUrl($tenant, "/importar-dados/{$slug}/exportar.xlsx"));
        $response->assertOk();
        activateTenant($tenant);
        $type = TabularImporter::type($slug);
        $plan = app(TabularImporter::class)->plan($type, $response->baseResponse->getFile()->getPathname(), 'xlsx', 'update');

        // Dados atuais exportados e importados de volta: nada muda (ou nada para importar).
        expect($plan->hasWork())->toBeFalse();
    }
});
