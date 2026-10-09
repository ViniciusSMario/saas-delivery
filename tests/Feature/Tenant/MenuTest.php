<?php

use App\Livewire\Tenant\Menu\AddonGroups\Form as AddonGroupForm;
use App\Livewire\Tenant\Menu\Categories;
use App\Livewire\Tenant\Menu\Combos\Form as ComboForm;
use App\Livewire\Tenant\Menu\Products\Form as ProductForm;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Menu\Actions\SaveProduct;
use App\Modules\Menu\Models\AddonGroup;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Combo;
use App\Modules\Menu\Models\Product;
use App\Support\Money;
use App\Tenancy\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

/** Cria categoria e produto direto no tenant (para os testes de leitura). */
function menuProduct(Tenant $tenant, array $attributes = [], ?string $category = null): Product
{
    return inTenant($tenant, function () use ($attributes, $category) {
        $cat = Category::firstOrCreate(['name' => $category ?? 'Pizzas']);

        return Product::create($attributes + [
            'category_id' => $cat->id,
            'name' => 'Produto '.Str::random(6),
            'price_cents' => Money::parse('39,90'),
        ]);
    });
}

beforeEach(function () {
    $this->a = tenantBySlug('teste1');
    $this->b = tenantBySlug('teste2');
});

it('só quem tem menu.manage gerencia o cardápio', function () {
    $caixa = createTenantUser($this->a, ['caixa']);
    $gerente = createTenantUser($this->a, ['gerente']);

    // Sessão limpa ao trocar de usuário: AuthenticateSession guarda o hash da senha de quem logou.
    foreach (['/produtos', '/categorias', '/adicionais', '/combos', '/produtos/novo'] as $path) {
        $this->flushSession();
        $this->actingAs($caixa)->get(tenantUrl($this->a, $path))->assertForbidden();
        $this->flushSession();
        $this->actingAs($gerente)->get(tenantUrl($this->a, $path))->assertOk();
    }
});

it('cria categorias e reordena', function () {
    $owner = createTenantUser(activateTenantAndReturn(provisionTenant('cat')));

    $component = Livewire::actingAs($owner)->test(Categories::class);
    foreach (['Pizzas', 'Bebidas', 'Sobremesas'] as $name) {
        $component->set('name', $name)->call('save')->assertHasNoErrors();
    }

    $bebidas = Category::where('name', 'Bebidas')->first();
    $component->call('move', $bebidas->id, -1);

    expect(Category::ordered()->pluck('name')->all())->toBe(['Bebidas', 'Pizzas', 'Sobremesas']);
});

it('não exclui categoria com produtos', function () {
    $product = menuProduct($this->a, category: 'Com produto');
    $owner = createTenantUser($this->a);
    activateTenant($this->a);

    Livewire::actingAs($owner)->test(Categories::class)
        ->call('delete', $product->category_id)
        ->assertHasErrors(['list']);

    expect(Category::find($product->category_id))->not->toBeNull();
});

it('cadastra produto com preço, fotos e grupos de adicionais', function () {
    $owner = createTenantUser($this->a);
    activateTenant($this->a);
    $category = Category::firstOrCreate(['name' => 'Pizzas']);
    $borda = AddonGroup::create(['name' => 'Borda', 'min_choices' => 1, 'max_choices' => 1]);
    $extras = AddonGroup::create(['name' => 'Extras', 'min_choices' => 0, 'max_choices' => 3]);

    Livewire::actingAs($owner)->test(ProductForm::class)
        ->set('category_id', $category->id)
        ->set('code', 'P101')
        ->set('name', 'Pizza Calabresa')
        ->set('price', '1.049,90')
        ->set('addonGroupIds', [$extras->id, $borda->id])
        ->set('newImages', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('menu.products.index'));

    $product = Product::where('code', 'P101')->firstOrFail();

    expect($product->price_cents->cents)->toBe(104990)
        ->and($product->addonGroups->pluck('name')->all())->toBe(['Extras', 'Borda'])
        ->and($product->images)->toHaveCount(2)
        ->and(Storage::disk('tenant')->path($product->images->first()->path))->toContain('tenants/'.$this->a->id.'/products/'.$product->id.'/')
        ->and(AuditLog::where('action', 'product.created')->where('subject_id', $product->id)->exists())->toBeTrue();
});

it('valida preço e limite de fotos', function () {
    $owner = createTenantUser($this->a);
    activateTenant($this->a);

    Livewire::actingAs($owner)->test(ProductForm::class)
        ->set('name', 'X')->set('price', 'abc')->call('save')->assertHasErrors(['price']);

    $product = menuProduct($this->a);
    expect(fn () => app(SaveProduct::class)->handle($product, [
        'category_id' => $product->category_id, 'name' => $product->name, 'price' => $product->price_cents,
    ], newImages: array_fill(0, SaveProduct::MAX_IMAGES + 1, UploadedFile::fake()->image('x.jpg'))))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

it('código do produto é único no estabelecimento, mas pode repetir em outro', function () {
    menuProduct($this->a, ['code' => 'DUP1']);
    menuProduct($this->b, ['code' => 'DUP1']);
    $owner = createTenantUser($this->a);
    activateTenant($this->a);

    Livewire::actingAs($owner)->test(ProductForm::class)
        ->set('name', 'Outro')->set('price', '10')->set('code', 'dup1')
        ->call('save')->assertHasErrors(['code']);
});

it('libera o código quando o produto é excluído (soft delete)', function () {
    $product = menuProduct($this->a, ['code' => 'REUSO']);
    activateTenant($this->a);

    app(SaveProduct::class)->delete($product);

    expect(Product::withTrashed()->find($product->id)->trashed())->toBeTrue();
    expect(fn () => menuProduct($this->a, ['code' => 'REUSO']))->not->toThrow(Throwable::class);
});

it('busca por parte do nome usando o índice trigram, e por código', function () {
    menuProduct($this->a, ['name' => 'Pizza Calabresa Especial', 'code' => 'CALAB1']);

    inTenant($this->a, function () {
        expect(Product::search('calab')->pluck('name')->all())->toContain('Pizza Calabresa Especial')
            ->and(Product::search('calab1')->count())->toBe(1)
            ->and(Product::search('100%')->count())->toBe(0);

        $index = DB::connection('tenant')->table('pg_indexes')
            ->where('schemaname', DB::raw('current_schema()'))
            ->where('indexname', 'products_name_trgm')
            ->value('indexdef');

        expect($index)->toContain('gin_trgm_ops');
    });
});

it('valida grupo de adicionais: mínimo não pode passar das opções ativas', function () {
    $owner = createTenantUser($this->a);
    activateTenant($this->a);

    Livewire::actingAs($owner)->test(AddonGroupForm::class)
        ->set('name', 'Molhos')
        ->set('min_choices', 2)
        ->set('max_choices', 2)
        ->set('addons', [['id' => null, 'name' => 'Barbecue', 'price' => '0', 'is_active' => true]])
        ->call('save')
        ->assertHasErrors(['min_choices']);

    Livewire::actingAs($owner)->test(AddonGroupForm::class)
        ->set('name', 'Molhos')
        ->set('min_choices', 0)
        ->set('max_choices', 2)
        ->set('addons', [
            ['id' => null, 'name' => 'Barbecue', 'price' => '0', 'is_active' => true],
            ['id' => null, 'name' => 'Cheddar', 'price' => '3,50', 'is_active' => true],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $group = AddonGroup::where('name', 'Molhos')->latest('id')->first();
    expect($group->addons->pluck('price_cents')->map->cents->all())->toBe([0, 350]);
});

it('monta combo e calcula a economia', function () {
    $pizza = menuProduct($this->a, ['name' => 'Pizza Grande', 'price_cents' => Money::parse('50')]);
    $refri = menuProduct($this->a, ['name' => 'Refri 2L', 'price_cents' => Money::parse('12')], 'Bebidas');
    $owner = createTenantUser($this->a);
    activateTenant($this->a);

    Livewire::actingAs($owner)->test(ComboForm::class)
        ->set('name', 'Combo Família')
        ->set('price', '99,90')
        ->set('items', [['product_id' => $pizza->id, 'quantity' => 2], ['product_id' => $refri->id, 'quantity' => 1]])
        ->call('save')
        ->assertHasNoErrors();

    $combo = Combo::where('name', 'Combo Família')->with('items.product')->firstOrFail();

    expect($combo->regularPrice()->cents)->toBe(11200)
        ->and($combo->savings()->format())->toBe('R$ 12,10')
        ->and($combo->isAvailable())->toBeTrue();

    $refri->update(['is_available' => false]);
    expect($combo->fresh('items.product')->isAvailable())->toBeFalse();
});

it('mostra o cardápio público pelo subdomínio e pelo slug, só com o que está ativo', function () {
    $visible = menuProduct($this->a, ['name' => 'Visível '.Str::random(4), 'description' => 'Molho da casa']);
    $soldOut = menuProduct($this->a, ['name' => 'Esgotado '.Str::random(4), 'is_available' => false]);
    $inactive = menuProduct($this->a, ['name' => 'Inativo '.Str::random(4), 'is_active' => false]);
    $deleted = menuProduct($this->a, ['name' => 'Excluído '.Str::random(4)]);
    inTenant($this->a, fn () => $deleted->delete());
    $hiddenCategory = menuProduct($this->a, ['name' => 'Categoria oculta '.Str::random(4)], 'Oculta');
    inTenant($this->a, fn () => Category::where('name', 'Oculta')->update(['is_active' => false]));

    foreach ([tenantUrl($this->a, '/cardapio'), 'http://localhost/cardapio/teste1'] as $url) {
        $this->get($url)->assertOk()
            ->assertSee($visible->name)
            ->assertSee('Molho da casa')
            ->assertSee('R$ 39,90')
            ->assertSee($soldOut->name)
            ->assertSee('Esgotado')
            ->assertDontSee($inactive->name)
            ->assertDontSee($deleted->name)
            ->assertDontSee($hiddenCategory->name);
    }

    $this->get(tenantUrl($this->b, '/cardapio'))->assertOk()->assertDontSee($visible->name);
    $this->get('http://localhost/cardapio/teste2')->assertOk()->assertDontSee($visible->name);
});

it('serve a foto do produto só no cardápio do próprio estabelecimento', function () {
    $owner = createTenantUser($this->a);
    activateTenant($this->a);
    $product = menuProduct($this->a);
    app(SaveProduct::class)->handle($product, [
        'category_id' => $product->category_id, 'name' => $product->name, 'price' => $product->price_cents,
    ], newImages: [UploadedFile::fake()->image('foto.jpg')]);
    $imageId = $product->images()->value('id');
    app(TenantContext::class)->reset();

    $this->get(tenantUrl($this->a, '/cardapio/imagens/'.$imageId))->assertOk();
    $this->get('http://localhost/cardapio/teste1/imagens/'.$imageId)->assertOk();

    // B não tem esse id (ou tem outra foto): nunca devolve o arquivo de A.
    $responseB = $this->get(tenantUrl($this->b, '/cardapio/imagens/'.$imageId));
    expect($responseB->status())->toBe(404);

    // Produto inativo: a foto sai do cardápio público, mas continua na gestão.
    inTenant($this->a, fn () => $product->update(['is_active' => false]));
    $this->get(tenantUrl($this->a, '/cardapio/imagens/'.$imageId))->assertNotFound();
    $this->actingAs($owner)->get(tenantUrl($this->a, '/produtos/imagens/'.$imageId))->assertOk();
});

it('mostra aberto/fechado conforme o horário e o fuso do estabelecimento', function () {
    $tenant = provisionTenant('hora');
    inTenant($tenant, function () {
        App\Modules\Establishment\Models\OpeningHour::query()->update(['is_closed' => false, 'opens_at' => '00:00', 'closes_at' => '23:59']);
    });
    $this->get(tenantUrl($tenant, '/cardapio'))->assertSee('Aberto agora');

    inTenant($tenant, fn () => App\Modules\Establishment\Models\OpeningHour::query()->update(['is_closed' => true]));
    $this->get(tenantUrl($tenant, '/cardapio'))->assertSee('Fechado agora');
});

/** Ativa um tenant recém-criado e o devolve (para encadear). */
function activateTenantAndReturn(Tenant $tenant): Tenant
{
    activateTenant($tenant);

    return $tenant;
}
