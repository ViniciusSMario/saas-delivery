<?php

namespace App\Modules\Menu\Import;

use App\Modules\Imports\SpreadsheetReader;
use App\Modules\Menu\Models\AddonGroup;
use App\Modules\Menu\Models\Category;
use App\Support\Money;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

/**
 * Planilha do cardápio (Excel ou CSV): leitura com cabeçalhos flexíveis ("Preço", "valor",
 * "PREÇO (R$)"...) e geração do modelo, que também serve para exportar o cardápio atual.
 */
class MenuSpreadsheet
{
    /** Coluna => [título no modelo, outros nomes aceitos (sem acento, minúsculas)]. */
    public const PRODUCT_COLUMNS = [
        'category' => ['Categoria', ['categoria', 'categorias', 'secao', 'grupo do cardapio']],
        'name' => ['Nome', ['nome', 'produto', 'item', 'nome do produto']],
        'description' => ['Descrição', ['descricao', 'detalhes', 'ingredientes']],
        'price' => ['Preço', ['preco', 'valor', 'preco de venda', 'valor de venda']],
        'code' => ['Código', ['codigo', 'cod', 'sku', 'referencia', 'ref']],
        'available' => ['Disponível', ['disponivel', 'em estoque', 'ativo']],
        'featured' => ['Destaque', ['destaque', 'destacado', 'em destaque']],
        'addons' => ['Adicionais', ['adicionais', 'grupos de adicionais', 'complementos', 'opcoes']],
        'photo' => ['Foto', ['foto', 'imagem', 'arquivo da foto']],
    ];

    public const ADDON_COLUMNS = [
        'group' => ['Grupo', ['grupo', 'grupo de adicionais', 'complemento']],
        'min' => ['Mínimo', ['minimo', 'min', 'escolha minima']],
        'max' => ['Máximo', ['maximo', 'max', 'escolha maxima']],
        'option' => ['Opção', ['opcao', 'adicional', 'item', 'nome']],
        'price' => ['Preço', ['preco', 'valor']],
    ];

    public const REQUIRED_PRODUCT_COLUMNS = ['category', 'name', 'price'];

    /**
     * Lê a planilha.
     *
     * @return array{products: list<array{line: int, values: array<string, mixed>}>, addons: list<array{line: int, values: array<string, mixed>}>, columns: list<string>, addonSheet: bool}
     *
     * @throws RuntimeException com a mensagem para o usuário (arquivo ilegível ou sem as colunas)
     */
    public function read(string $path, string $extension): array
    {
        $reader = app(SpreadsheetReader::class);
        $sheets = $reader->sheets($path, $extension);

        $productRows = null;
        $addonRows = null;

        foreach ($sheets as $name => $rows) {
            $plain = self::plain($name);

            if ($addonRows === null && preg_match('/adicion|complement|opco/', $plain)) {
                $addonRows = $rows;
            } elseif ($productRows === null && ! preg_match('/instruc|como preencher|ajuda|leia/', $plain)) {
                $productRows = $rows;
            }
        }

        $products = $reader->table($productRows ?? [], self::PRODUCT_COLUMNS);

        if ($products === null) {
            throw new RuntimeException('Não encontramos a linha de títulos dos produtos. Use o modelo: a primeira linha precisa ter Categoria, Nome e Preço.');
        }

        $missing = array_diff(self::REQUIRED_PRODUCT_COLUMNS, $products['columns']);

        if ($missing !== []) {
            $labels = array_map(fn ($key) => self::PRODUCT_COLUMNS[$key][0], $missing);

            throw new RuntimeException('Faltam as colunas: '.implode(', ', $labels).'. Use os títulos do modelo.');
        }

        $addons = $addonRows !== null ? $reader->table($addonRows, self::ADDON_COLUMNS) : null;

        return [
            'products' => $products['rows'],
            'addons' => $addons['rows'] ?? [],
            'columns' => $products['columns'],
            'addonSheet' => $addons !== null,
        ];
    }

    /** Sem acento, minúsculas e espaços simples (para comparar títulos e nomes). */
    public static function plain(?string $value): string
    {
        return SpreadsheetReader::plain($value);
    }

    // ---------- Modelo e exportação ----------

    /**
     * Gera a planilha no formato de importação. Sem cardápio: o modelo com exemplos.
     * Com o cardápio atual: exportação pronta para editar e importar de novo.
     *
     * @param  Collection<int, Category>|null  $categories  com products.addonGroups
     * @param  Collection<int, AddonGroup>|null  $groups  com addons
     */
    public function writeXlsx(string $path, ?Collection $categories = null, ?Collection $groups = null): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $head = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('C2410C');

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Produtos');
        $sheet->setColumnWidth(18, 1);
        $sheet->setColumnWidth(28, 2);
        $sheet->setColumnWidth(44, 3);
        $sheet->setColumnWidth(10, 4, 5, 6, 7);
        $sheet->setColumnWidth(26, 8);
        $sheet->setColumnWidth(16, 9);
        $writer->addRow(Row::fromValues(array_column(self::PRODUCT_COLUMNS, 0), $head));

        foreach ($categories ? $this->productRows($categories) : $this->exampleProducts() as $values) {
            $writer->addRow(Row::fromValues($values));
        }

        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Adicionais');
        $sheet->setColumnWidth(24, 1);
        $sheet->setColumnWidth(10, 2, 3);
        $sheet->setColumnWidth(26, 4);
        $sheet->setColumnWidth(10, 5);
        $writer->addRow(Row::fromValues(array_column(self::ADDON_COLUMNS, 0), $head));

        foreach ($groups ? $this->addonRows($groups) : $this->exampleAddons() as $values) {
            $writer->addRow(Row::fromValues($values));
        }

        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Como preencher');
        $sheet->setColumnWidth(110, 1);
        $bold = (new Style)->setFontBold();

        foreach ($this->instructions() as $line) {
            $writer->addRow(Row::fromValues([$line[0]], $line[1] ? $bold : null));
        }

        $writer->close();
    }

    /** CSV (só produtos; adicionais e fotos ficam para o Excel e o .zip). */
    public function writeCsv(string $path, ?Collection $categories = null): void
    {
        $options = new \OpenSpout\Writer\CSV\Options;
        $options->FIELD_DELIMITER = ';';
        $writer = new CsvWriter($options);
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_column(self::PRODUCT_COLUMNS, 0)));

        foreach ($categories ? $this->productRows($categories) : $this->exampleProducts() as $values) {
            $writer->addRow(Row::fromValues($values));
        }

        $writer->close();
    }

    private function productRows(Collection $categories): \Generator
    {
        foreach ($categories as $category) {
            foreach ($category->products as $product) {
                yield [
                    $category->name,
                    $product->name,
                    (string) $product->description,
                    self::money($product->price_cents),
                    (string) $product->code,
                    $product->is_available ? 'sim' : 'não',
                    $product->is_featured ? 'sim' : 'não',
                    $product->addonGroups->pluck('name')->implode('; '),
                    '',
                ];
            }
        }
    }

    private function addonRows(Collection $groups): \Generator
    {
        foreach ($groups as $group) {
            foreach ($group->addons as $i => $addon) {
                yield [$group->name, $i === 0 ? $group->min_choices : '', $i === 0 ? $group->max_choices : '', $addon->name, self::money($addon->price_cents)];
            }
        }
    }

    private function exampleProducts(): array
    {
        return [
            ['Pizzas', 'Margherita', 'Molho de tomate, muçarela e manjericão', '49,90', '101', 'sim', 'sim', 'Borda recheada', 'margherita.jpg'],
            ['Pizzas', 'Calabresa', 'Calabresa fatiada e cebola', '52,90', '102', 'sim', 'não', 'Borda recheada', ''],
            ['Lanches', 'X-Burguer', 'Pão, hambúrguer 150 g, queijo e salada', '28,00', '201', 'sim', 'não', 'Adicionais do lanche; Ponto da carne', ''],
            ['Bebidas', 'Refrigerante lata', '350 ml', '6,00', '301', 'sim', 'não', '', ''],
        ];
    }

    private function exampleAddons(): array
    {
        return [
            ['Borda recheada', 0, 1, 'Catupiry', '8,00'],
            ['Borda recheada', '', '', 'Cheddar', '8,00'],
            ['Adicionais do lanche', 0, 3, 'Bacon', '5,00'],
            ['Adicionais do lanche', '', '', 'Ovo', '3,00'],
            ['Adicionais do lanche', '', '', 'Queijo extra', '4,00'],
            ['Ponto da carne', 1, 1, 'Mal passada', '0'],
            ['Ponto da carne', '', '', 'Ao ponto', '0'],
            ['Ponto da carne', '', '', 'Bem passada', '0'],
        ];
    }

    /** @return list<array{0: string, 1: bool}> */
    private function instructions(): array
    {
        return [
            ['Como preencher a planilha do cardápio', true],
            ['', false],
            ['Aba Produtos (uma linha por produto)', true],
            ['Categoria, Nome e Preço são obrigatórios. Categoria que ainda não existe é criada sozinha.', false],
            ['Preço: 29,90 (pode ter R$ e ponto de milhar). Disponível e Destaque: sim ou não (em branco = sim / não).', false],
            ['Código: opcional, até 20 caracteres. Se tiver, é por ele que o produto é reconhecido nas próximas importações.', false],
            ['Adicionais: nomes dos grupos da aba Adicionais (ou já cadastrados), separados por ponto e vírgula.', false],
            ['Foto: nome do arquivo dentro do .zip de fotos (sem o nome, procuramos pelo código ou pelo nome do produto).', false],
            ['', false],
            ['Aba Adicionais (uma linha por opção)', true],
            ['Grupo e Opção são obrigatórios. Mínimo e Máximo: quantas opções o cliente escolhe (basta na primeira linha do grupo).', false],
            ['Mínimo 0 = opcional; mínimo 1 = obrigatório. Preço em branco = grátis.', false],
            ['', false],
            ['Produtos que já existem', true],
            ['O produto é reconhecido pelo código ou pelo nome. Ao importar você escolhe: atualizar os que já existem ou só criar os novos.', false],
            ['Célula em branco não muda nada no produto que já existe (para tirar a descrição ou os adicionais, use o painel).', false],
            ['', false],
            ['Antes de gravar, o sistema mostra uma prévia com o que vai ser criado e atualizado e os erros por linha.', false],
            ['A última importação pode ser desfeita em Produtos → Importar planilha.', false],
        ];
    }

    private static function money(Money $money): string
    {
        return number_format($money->cents / 100, 2, ',', '.');
    }
}
