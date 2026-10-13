<?php

use App\Livewire\Tenant\Menu\Import;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Menu\Import\MenuImporter;
use App\Modules\Menu\Import\MenuImportPlanner;
use App\Modules\Menu\Import\MenuSpreadsheet;
use App\Modules\Menu\Import\PhotoArchive;
use App\Modules\Menu\Models\AddonGroup;
use App\Modules\Menu\Models\Category;
use App\Modules\Imports\Models\DataImport;
use App\Modules\Menu\Models\Product;
use App\Modules\Menu\Models\ProductImage;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Planilha .xlsx de teste: [aba => [títulos, ...linhas]]. */
function menuSheet(array $sheets): string
{
    $path = tempnam(sys_get_temp_dir(), 'teste').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $first = true;

    foreach ($sheets as $name => $rows) {
        $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName($name);
        $first = false;

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
    }

    $writer->close();

    return $path;
}

const PRODUCT_HEAD = ['Categoria', 'Nome', 'Descrição', 'Preço', 'Código', 'Disponível', 'Destaque', 'Adicionais', 'Foto'];

/** Lê, planeja e (se pedido) grava, como a tela faz. */
function importMenu(string $path, string $mode = 'update', ?string $zip = null, bool $apply = true, string $ext = 'xlsx'): array
{
    $sheet = app(MenuSpreadsheet::class)->read($path, $ext);
    $photos = $zip ? new PhotoArchive($zip) : null;
    $plan = app(MenuImportPlanner::class)->plan($sheet, $mode, $photos);
    $import = $apply && ! $plan->hasErrors() ? app(MenuImporter::class)->apply($plan, $mode, basename($path), $photos, $zip ? 'fotos.zip' : null, null) : null;
    $photos?->close();

    return [$plan, $import];
}

/** PNG de verdade (o importador confere que é imagem). */
function pngBytes(): string
{
    $image = imagecreatetruecolor(4, 4);
    ob_start();
    imagepng($image);
    imagedestroy($image);

    return ob_get_clean();
}

beforeEach(fn () => Storage::fake('tenant'));

it('o modelo da planilha importa categorias, produtos e adicionais na ordem certa', function () {
    salon();
    $path = tempnam(sys_get_temp_dir(), 'modelo').'.xlsx';
    app(MenuSpreadsheet::class)->writeXlsx($path);

    [$plan, $import] = importMenu($path);

    expect($plan->errors)->toBe([])
        ->and($plan->counts())->toMatchArray(['products_created' => 4, 'categories_created' => 3, 'groups_created' => 3])
        ->and($import->summary['products_created'])->toBe(4);

    $burger = Product::where('name', 'X-Burguer')->with('addonGroups', 'category')->sole();
    expect($burger->category->name)->toBe('Lanches')
        ->and($burger->price_cents->format())->toBe('R$ 28,00')
        ->and($burger->addonGroups->pluck('name')->all())->toBe(['Adicionais do lanche', 'Ponto da carne'])
        ->and(Product::where('name', 'Margherita')->value('is_featured'))->toBeTrue();

    $ponto = AddonGroup::where('name', 'Ponto da carne')->with('addons')->sole();
    expect([$ponto->min_choices, $ponto->max_choices])->toBe([1, 1])
        ->and($ponto->addons->pluck('name')->all())->toBe(['Mal passada', 'Ao ponto', 'Bem passada'])
        ->and(AddonGroup::where('name', 'Borda recheada')->first()->addons->pluck('price_cents')->map->format()->all())->toBe(['R$ 8,00', 'R$ 8,00'])
        ->and(AuditLog::where('action', 'menu.imported')->count())->toBe(1);
});

it('atualiza o que já existe (sem apagar o que veio em branco) ou só cria os novos', function () {
    ['burger' => $burger] = salon();
    $burger->update(['description' => 'Pão e carne', 'code' => 'H1']);

    $path = menuSheet(['Produtos' => [
        PRODUCT_HEAD,
        ['Pratos', 'Hambúrguer duplo', '', '35,00', 'h1', 'não', '', '', ''], // pelo código: renomeia, muda preço, esgota
        ['Pratos', 'Refrigerante', 'Lata 350 ml', 8, '', 'sim', '', '', ''],    // pelo nome: igual, só a descrição muda
        ['Sobremesas', 'Pudim', 'Pudim de leite', 'R$ 12,50', '', '', 'sim', '', ''],
    ]]);

    [$skip] = importMenu($path, 'skip', apply: false);
    expect(collect($skip->products)->pluck('action')->all())->toBe(['skip', 'skip', 'create']);

    [$plan] = importMenu($path);
    expect(collect($plan->products)->pluck('action')->all())->toBe(['update', 'update', 'create'])
        ->and($plan->products[0]['changes'])->toBe(['nome: Hambúrguer → Hambúrguer duplo', 'preço: R$ 30,00 → R$ 35,00', 'código: H1 → h1', 'fica esgotado']);

    $burger->refresh();
    expect($burger->only(['name', 'description', 'is_available']))->toBe(['name' => 'Hambúrguer duplo', 'description' => 'Pão e carne', 'is_available' => false])
        ->and($burger->price_cents->format())->toBe('R$ 35,00')
        ->and(Product::where('name', 'Refrigerante')->value('description'))->toBe('Lata 350 ml')
        ->and(Product::where('name', 'Pudim')->sole()->only(['is_featured', 'is_available']))->toBe(['is_featured' => true, 'is_available' => true])
        ->and(Category::where('name', 'Sobremesas')->exists())->toBeTrue();

    // A mesma planilha de novo: nada a fazer.
    [$again] = importMenu($path, apply: false);
    expect($again->hasWork())->toBeFalse()->and(collect($again->products)->pluck('action')->unique()->all())->toBe(['same']);
});

it('mostra os erros por linha e não grava nada quando a planilha tem problema', function () {
    salon();
    $before = Product::count();

    $path = menuSheet([
        'Produtos' => [
            PRODUCT_HEAD,
            ['Lanches', 'X-Salada', '', '', 'X1', '', '', '', ''],
            ['Lanches', 'X-Bacon', '', 'abc', 'X1', '', '', '', ''],
            ['', 'Sem categoria', '', '10', '', 'talvez', '', 'Borda fantasma', ''],
            ['Lanches', 'X-Tudo', '', '30', 'X1', '', '', '', ''],
        ],
        'Adicionais' => [['Grupo', 'Mínimo', 'Máximo', 'Opção', 'Preço'], ['Molhos', 3, 2, 'Barbecue', '1,00']],
    ]);

    [$plan, $import] = importMenu($path);

    expect($import)->toBeNull()->and(Product::count())->toBe($before)->and(DataImport::count())->toBe(0)
        ->and($plan->errors)->toBe([
            'Adicionais, grupo Molhos: o Máximo precisa ser pelo menos 1 e não pode ser menor que o Mínimo.',
            'Produtos, linha 2: falta o Preço.',
            'Produtos, linha 3: preço inválido ("abc"), use 29,90; o código X1 já está na linha 2.',
            'Produtos, linha 4: falta a Categoria; Disponível deve ser sim ou não; o grupo de adicionais "Borda fantasma" não existe (crie na aba Adicionais).',
            'Produtos, linha 5: o código X1 já está na linha 3.',
        ]);

    // Sem as colunas obrigatórias: aviso antes de qualquer conferência.
    expect(fn () => app(MenuSpreadsheet::class)->read(menuSheet(['Plan1' => [['Item', 'Valor de custo'], ['A', 1]]]), 'xlsx'))
        ->toThrow(RuntimeException::class, 'Não encontramos a linha de títulos');
    expect(fn () => app(MenuSpreadsheet::class)->read(menuSheet(['Plan1' => [['Produto', 'Descrição'], ['A', 'b']]]), 'xlsx'))
        ->toThrow(RuntimeException::class, 'Faltam as colunas: Categoria, Preço.');
});

it('lê o CSV do Excel brasileiro (ponto e vírgula, acentos em Windows-1252 e títulos diferentes)', function () {
    salon();
    $csv = "Seção;Produto;Ingredientes;Valor (R$)\r\nPorções;Batata frita;Com cheddar e bacon;R$ 1.234,56\r\nPorções;Calabresa acebolada;;29.9\r\n";
    $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
    file_put_contents($path, mb_convert_encoding($csv, 'Windows-1252', 'UTF-8'));

    [$plan] = importMenu($path, ext: 'csv');

    expect($plan->errors)->toBe([])
        ->and(Product::where('name', 'Batata frita')->sole()->price_cents->format())->toBe('R$ 1.234,56')
        ->and(Product::where('name', 'Batata frita')->value('description'))->toBe('Com cheddar e bacon')
        ->and(Product::where('name', 'Calabresa acebolada')->sole()->price_cents->format())->toBe('R$ 29,90')
        ->and(Category::where('name', 'Porções')->exists())->toBeTrue();
});

it('importa as fotos do .zip pelo nome, código ou coluna Foto, sem duplicar ao importar de novo', function () {
    salon();
    $zip = tempnam(sys_get_temp_dir(), 'fotos').'.zip';
    $archive = new ZipArchive;
    $archive->open($zip, ZipArchive::CREATE);
    $archive->addFromString('fotos/Pizza Margherita.PNG', pngBytes());  // pela coluna Foto
    $archive->addFromString('201.png', pngBytes());                       // pelo código
    $archive->addFromString('Pudim.png', pngBytes());                     // pelo nome
    $archive->addFromString('virus.exe', 'nada');
    $archive->addFromString('falsa.jpg', 'não sou imagem');
    $archive->addFromString('__MACOSX/._201.png', 'lixo');
    $archive->close();

    $path = menuSheet(['Produtos' => [
        PRODUCT_HEAD,
        ['Pizzas', 'Margherita', '', '49,90', '101', '', '', '', 'pizza margherita.jpg'],
        ['Lanches', 'X-Burguer', '', '28', '201', '', '', '', ''],
        ['Doces', 'Pudim', '', '12', '', '', '', '', ''],
        ['Doces', 'Mousse', '', '12', '', '', '', '', 'mousse.jpg'],
    ]]);

    [$plan, $import] = importMenu($path, zip: $zip);

    expect($plan->counts()['photos'])->toBe(3)
        ->and($plan->warnings)->toContain('Produtos, linha 5: a foto mousse.jpg não está no .zip.')
        ->and(collect($plan->warnings)->first(fn ($w) => str_starts_with($w, 'Arquivos ignorados')))->toContain('virus.exe')
        ->and(collect($plan->warnings)->first(fn ($w) => str_contains($w, 'não combinam')))->toContain('falsa');

    expect(ProductImage::count())->toBe(3)->and($import->summary['photos'])->toBe(3);
    $image = Product::where('code', '201')->sole()->images()->sole();
    expect($image->path)->toStartWith('products/')->toEndWith('.png');
    Storage::disk('tenant')->assertExists($image->path);

    [$again] = importMenu($path, zip: $zip);
    expect(ProductImage::count())->toBe(3)->and($again->counts()['photos'])->toBe(0);
});

it('desfaz a última importação: tira o que criou e volta o que alterou', function () {
    ['burger' => $burger] = salon();
    $zip = tempnam(sys_get_temp_dir(), 'fotos').'.zip';
    $archive = new ZipArchive;
    $archive->open($zip, ZipArchive::CREATE);
    $archive->addFromString('Pudim.png', pngBytes());
    $archive->close();

    [, $first] = importMenu(menuSheet(['Produtos' => [PRODUCT_HEAD, ['Pratos', 'Hambúrguer', '', '31', '', '', '', '', '']]]));
    [, $second] = importMenu(menuSheet([
        'Produtos' => [
            PRODUCT_HEAD,
            ['Pratos', 'Hambúrguer', 'Novo texto', '40', '', '', '', 'Ponto', ''],
            ['Doces', 'Pudim', '', '12', '', '', '', '', ''],
        ],
        'Adicionais' => [['Grupo', 'Mínimo', 'Máximo', 'Opção', 'Preço'], ['Ponto', 1, 1, 'Ao ponto', '']],
    ]), zip: $zip);

    expect(MenuImporter::canUndo($first))->toBeFalse()->and(MenuImporter::canUndo($second))->toBeTrue();
    $imagePath = ProductImage::sole()->path;

    app(MenuImporter::class)->undo($second, null);

    $burger->refresh();
    expect($burger->price_cents->format())->toBe('R$ 31,00')
        ->and($burger->description)->toBeNull()
        ->and($burger->addonGroups()->count())->toBe(0)
        ->and(Product::withTrashed()->where('name', 'Pudim')->exists())->toBeFalse()
        ->and(Category::where('name', 'Doces')->exists())->toBeFalse()
        ->and(AddonGroup::count())->toBe(0)
        ->and(ProductImage::count())->toBe(0)
        ->and($second->fresh()->status)->toBe('undone')
        ->and(MenuImporter::canUndo($first->fresh()))->toBeTrue();
    Storage::disk('tenant')->assertMissing($imagePath);
});

it('a tela confere a planilha antes de gravar, importa, baixa o modelo e o cardápio atual', function () {
    ['tenant' => $tenant] = salon();
    $gerente = createTenantUser($tenant, ['gerente']);
    $path = menuSheet(['Produtos' => [PRODUCT_HEAD, ['Bebidas', 'Suco de laranja', '', '9,00', '', '', '', '', ''], ['Pratos', 'Hambúrguer', '', '32', '', '', '', '', '']]]);

    $this->actingAs($gerente);
    $page = Livewire::actingAs($gerente)->test(Import::class)
        ->set('file', UploadedFile::fake()->createWithContent('cardapio.xlsx', file_get_contents($path)))
        ->assertHasNoErrors()
        ->assertSee('Produtos novos')->assertSee('Suco de laranja')->assertSee('preço: R$ 30,00 → R$ 32,00')->assertSee('Categorias novas:');
    expect(Product::where('name', 'Suco de laranja')->exists())->toBeFalse(); // só a prévia

    $page->call('apply')->assertSee('Importação concluída.')->assertSee('1 produto criado · 1 produto atualizado · 1 categoria nova')->assertSee('Desfazer');
    expect(Product::where('name', 'Suco de laranja')->exists())->toBeTrue();

    // Arquivo que não é planilha.
    Livewire::actingAs($gerente)->test(Import::class)
        ->set('file', UploadedFile::fake()->createWithContent('cardapio.xlsx', 'não sou excel'))
        ->assertHasErrors('file')->assertSee('Não conseguimos abrir a planilha');

    // Modelo e exportação (a exportação lê de volta igual ao cardápio).
    $this->get(tenantUrl($tenant, '/produtos/importar/modelo.xlsx'))->assertOk()->assertDownload('modelo-cardapio.xlsx');
    $export = $this->get(tenantUrl($tenant, '/produtos/exportar.xlsx'));
    $export->assertOk();
    $exported = app(MenuSpreadsheet::class)->read($export->baseResponse->getFile()->getPathname(), 'xlsx');
    expect(collect($exported['products'])->pluck('values.name')->all())->toContain('Hambúrguer', 'Suco de laranja');
});

it('só quem cuida do cardápio importa', function () {
    ['tenant' => $tenant] = salon();
    $caixa = createTenantUser($tenant, ['caixa']);

    $this->actingAs($caixa);
    $this->get(tenantUrl($tenant, '/produtos/importar'))->assertForbidden();
    $this->get(tenantUrl($tenant, '/produtos/exportar.xlsx'))->assertForbidden();
});
