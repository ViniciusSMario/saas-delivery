<?php

namespace App\Modules\Menu\Import;

use App\Modules\Access\Models\User;
use App\Modules\Imports\Models\DataImport;
use Illuminate\Validation\ValidationException;

/**
 * Cardápios prontos por tipo de negócio, para o primeiro acesso: entram pelo mesmo caminho da
 * planilha (MenuImportPlanner + MenuImporter), então aparecem no histórico e podem ser desfeitos.
 * Preços são exemplos: o restaurante ajusta depois.
 */
class MenuTemplates
{
    /** @return array<string, array{name: string, icon: string, text: string}> */
    public static function segments(): array
    {
        return [
            'pizzaria' => ['name' => 'Pizzaria', 'icon' => 'flame', 'text' => 'Pizzas salgadas e doces, bordas recheadas e bebidas.'],
            'hamburgueria' => ['name' => 'Hamburgueria', 'icon' => 'utensils', 'text' => 'Lanches, porções, ponto da carne e adicionais.'],
            'acai' => ['name' => 'Açaí e sorvetes', 'icon' => 'gift', 'text' => 'Copos por tamanho, coberturas e complementos.'],
            'marmitaria' => ['name' => 'Restaurante e marmitas', 'icon' => 'bag', 'text' => 'Pratos do dia, marmitas por tamanho e guarnições.'],
            'bar' => ['name' => 'Bar e petiscos', 'icon' => 'table', 'text' => 'Petiscos, chopes, drinks e bebidas.'],
        ];
    }

    /** Aplica o modelo: categorias, produtos e adicionais (só cria, nunca mexe no que já existe). */
    public function apply(string $segment, ?User $user): DataImport
    {
        $data = $this->data($segment) ?? throw ValidationException::withMessages(['segment' => 'Modelo não encontrado.']);

        $sheet = [
            'products' => array_map(fn ($row, $i) => ['line' => $i + 2, 'values' => array_combine(
                ['category', 'name', 'description', 'price', 'featured', 'addons'],
                $row,
            )], $data['products'], array_keys($data['products'])),
            'addons' => array_map(fn ($row, $i) => ['line' => $i + 2, 'values' => array_combine(['group', 'min', 'max', 'option', 'price'], $row)], $data['addons'], array_keys($data['addons'])),
            'columns' => ['category', 'name', 'description', 'price', 'featured', 'addons'],
            'addonSheet' => true,
        ];

        $plan = app(MenuImportPlanner::class)->plan($sheet, 'skip');

        if ($plan->hasErrors()) {
            throw ValidationException::withMessages(['segment' => $plan->errors[0]]);
        }

        return app(MenuImporter::class)->apply($plan, 'skip', 'Modelo: '.self::segments()[$segment]['name'], null, null, $user);
    }

    /** @return list<string> nomes dos produtos (para a prévia no assistente) */
    public function preview(string $segment): array
    {
        return array_column($this->data($segment)['products'] ?? [], 1);
    }

    /** @return array{products: list<array>, addons: list<array>}|null [categoria, nome, descrição, preço, destaque, adicionais] */
    private function data(string $segment): ?array
    {
        return match ($segment) {
            'pizzaria' => [
                'products' => [
                    ['Pizzas', 'Margherita', 'Molho de tomate, muçarela, tomate e manjericão.', '49,90', 'sim', 'Borda; Adicionais da pizza'],
                    ['Pizzas', 'Calabresa', 'Calabresa fatiada, cebola e azeitona.', '52,90', 'sim', 'Borda; Adicionais da pizza'],
                    ['Pizzas', 'Portuguesa', 'Presunto, ovo, cebola, ervilha e muçarela.', '56,90', '', 'Borda; Adicionais da pizza'],
                    ['Pizzas', 'Frango com catupiry', 'Frango desfiado e catupiry original.', '56,90', '', 'Borda; Adicionais da pizza'],
                    ['Pizzas', 'Quatro queijos', 'Muçarela, provolone, parmesão e gorgonzola.', '58,90', '', 'Borda; Adicionais da pizza'],
                    ['Pizzas doces', 'Chocolate', 'Chocolate ao leite e granulado.', '46,90', '', 'Borda'],
                    ['Pizzas doces', 'Romeu e Julieta', 'Goiabada e muçarela.', '46,90', '', 'Borda'],
                    ['Bebidas', 'Refrigerante 2 L', 'Coca-Cola, Guaraná ou Sprite.', '14,00', '', ''],
                    ['Bebidas', 'Refrigerante lata', '350 ml.', '6,00', '', ''],
                    ['Bebidas', 'Água sem gás', '500 ml.', '4,00', '', ''],
                ],
                'addons' => [
                    ['Borda', 1, 1, 'Tradicional', '0'],
                    ['Borda', '', '', 'Catupiry', '8,00'],
                    ['Borda', '', '', 'Cheddar', '8,00'],
                    ['Borda', '', '', 'Chocolate', '10,00'],
                    ['Adicionais da pizza', 0, 4, 'Bacon', '6,00'],
                    ['Adicionais da pizza', '', '', 'Azeitona', '3,00'],
                    ['Adicionais da pizza', '', '', 'Cebola caramelizada', '5,00'],
                ],
            ],
            'hamburgueria' => [
                'products' => [
                    ['Lanches', 'X-Burguer', 'Pão brioche, hambúrguer 150 g e queijo.', '26,00', 'sim', 'Ponto da carne; Adicionais do lanche'],
                    ['Lanches', 'X-Salada', 'Hambúrguer 150 g, queijo, alface, tomate e maionese da casa.', '29,00', '', 'Ponto da carne; Adicionais do lanche'],
                    ['Lanches', 'X-Bacon', 'Hambúrguer 150 g, queijo e bacon crocante.', '32,00', 'sim', 'Ponto da carne; Adicionais do lanche'],
                    ['Lanches', 'X-Tudo', 'Dois hambúrgueres, ovo, bacon, presunto, queijo e salada.', '39,00', '', 'Ponto da carne; Adicionais do lanche'],
                    ['Lanches', 'Smash duplo', 'Dois smash 90 g, cheddar e cebola na chapa.', '34,00', '', 'Adicionais do lanche'],
                    ['Porções', 'Batata frita', 'Porção de 400 g.', '22,00', '', 'Molhos'],
                    ['Porções', 'Batata com cheddar e bacon', 'Porção de 400 g.', '29,00', '', 'Molhos'],
                    ['Porções', 'Onion rings', 'Anéis de cebola empanados.', '24,00', '', 'Molhos'],
                    ['Bebidas', 'Refrigerante lata', '350 ml.', '6,00', '', ''],
                    ['Bebidas', 'Milk-shake', 'Chocolate, morango ou baunilha. 400 ml.', '18,00', '', ''],
                ],
                'addons' => [
                    ['Ponto da carne', 1, 1, 'Mal passada', '0'],
                    ['Ponto da carne', '', '', 'Ao ponto', '0'],
                    ['Ponto da carne', '', '', 'Bem passada', '0'],
                    ['Adicionais do lanche', 0, 5, 'Bacon', '5,00'],
                    ['Adicionais do lanche', '', '', 'Ovo', '3,00'],
                    ['Adicionais do lanche', '', '', 'Queijo extra', '4,00'],
                    ['Adicionais do lanche', '', '', 'Hambúrguer extra', '9,00'],
                    ['Molhos', 0, 3, 'Maionese da casa', '0'],
                    ['Molhos', '', '', 'Barbecue', '2,00'],
                    ['Molhos', '', '', 'Cheddar', '3,00'],
                ],
            ],
            'acai' => [
                'products' => [
                    ['Açaí', 'Açaí 300 ml', 'Escolha até 3 complementos grátis.', '16,00', 'sim', 'Complementos grátis; Coberturas; Adicionais do açaí'],
                    ['Açaí', 'Açaí 500 ml', 'Escolha até 3 complementos grátis.', '22,00', 'sim', 'Complementos grátis; Coberturas; Adicionais do açaí'],
                    ['Açaí', 'Açaí 700 ml', 'Escolha até 3 complementos grátis.', '28,00', '', 'Complementos grátis; Coberturas; Adicionais do açaí'],
                    ['Açaí', 'Barca de açaí 1 L', 'Para dividir. Até 3 complementos grátis.', '42,00', '', 'Complementos grátis; Coberturas; Adicionais do açaí'],
                    ['Sorvetes', 'Sorvete 2 bolas', 'Escolha os sabores no pedido (observação).', '14,00', '', 'Coberturas'],
                    ['Sorvetes', 'Milk-shake', 'Chocolate, morango ou baunilha. 400 ml.', '18,00', '', ''],
                    ['Bebidas', 'Água sem gás', '500 ml.', '4,00', '', ''],
                ],
                'addons' => [
                    ['Complementos grátis', 0, 3, 'Granola', '0'],
                    ['Complementos grátis', '', '', 'Banana', '0'],
                    ['Complementos grátis', '', '', 'Leite em pó', '0'],
                    ['Complementos grátis', '', '', 'Paçoca', '0'],
                    ['Complementos grátis', '', '', 'Morango', '0'],
                    ['Coberturas', 0, 2, 'Leite condensado', '0'],
                    ['Coberturas', '', '', 'Chocolate', '0'],
                    ['Coberturas', '', '', 'Morango', '0'],
                    ['Adicionais do açaí', 0, 5, 'Nutella', '6,00'],
                    ['Adicionais do açaí', '', '', 'Kit Kat', '4,00'],
                    ['Adicionais do açaí', '', '', 'Ninho trufado', '5,00'],
                ],
            ],
            'marmitaria' => [
                'products' => [
                    ['Marmitas', 'Marmita pequena', 'Arroz, feijão, uma carne e uma guarnição.', '18,00', 'sim', 'Carne; Guarnição'],
                    ['Marmitas', 'Marmita média', 'Arroz, feijão, uma carne e duas guarnições.', '22,00', 'sim', 'Carne; Guarnição'],
                    ['Marmitas', 'Marmita grande', 'Arroz, feijão, duas carnes e duas guarnições.', '27,00', '', 'Carne; Guarnição'],
                    ['Pratos', 'Prato feito', 'Arroz, feijão, bife acebolado, ovo, batata frita e salada.', '29,00', '', ''],
                    ['Pratos', 'Parmegiana de frango', 'Com arroz e batata frita.', '36,00', '', ''],
                    ['Pratos', 'Feijoada (sábado)', 'Com arroz, couve, farofa e laranja.', '39,00', '', ''],
                    ['Bebidas', 'Suco natural 500 ml', 'Laranja, limão ou maracujá.', '9,00', '', ''],
                    ['Bebidas', 'Refrigerante lata', '350 ml.', '6,00', '', ''],
                ],
                'addons' => [
                    ['Carne', 1, 2, 'Frango grelhado', '0'],
                    ['Carne', '', '', 'Bife acebolado', '0'],
                    ['Carne', '', '', 'Carne moída', '0'],
                    ['Carne', '', '', 'Linguiça', '0'],
                    ['Guarnição', 1, 2, 'Batata frita', '0'],
                    ['Guarnição', '', '', 'Farofa', '0'],
                    ['Guarnição', '', '', 'Salada', '0'],
                    ['Guarnição', '', '', 'Macarrão', '0'],
                ],
            ],
            'bar' => [
                'products' => [
                    ['Petiscos', 'Batata frita', 'Porção de 500 g.', '28,00', 'sim', 'Molhos'],
                    ['Petiscos', 'Calabresa acebolada', 'Com pão.', '36,00', 'sim', ''],
                    ['Petiscos', 'Isca de frango', 'Porção de 500 g.', '42,00', '', 'Molhos'],
                    ['Petiscos', 'Bolinho de bacalhau', '12 unidades.', '44,00', '', ''],
                    ['Petiscos', 'Torresmo', 'Porção de 300 g.', '32,00', '', ''],
                    ['Chopes e cervejas', 'Chope 300 ml', 'Pilsen.', '11,00', '', ''],
                    ['Chopes e cervejas', 'Cerveja 600 ml', 'Consulte as marcas.', '15,00', '', ''],
                    ['Drinks', 'Caipirinha', 'Limão, morango ou maracujá.', '22,00', '', 'Destilado'],
                    ['Drinks', 'Gin tônica', 'Com especiarias.', '28,00', '', ''],
                    ['Sem álcool', 'Refrigerante lata', '350 ml.', '6,00', '', ''],
                    ['Sem álcool', 'Água com gás', '500 ml.', '5,00', '', ''],
                ],
                'addons' => [
                    ['Molhos', 0, 3, 'Maionese da casa', '0'],
                    ['Molhos', '', '', 'Barbecue', '0'],
                    ['Molhos', '', '', 'Mostarda e mel', '0'],
                    ['Destilado', 1, 1, 'Cachaça', '0'],
                    ['Destilado', '', '', 'Vodca', '4,00'],
                    ['Destilado', '', '', 'Saquê', '6,00'],
                ],
            ],
            default => null,
        };
    }
}
