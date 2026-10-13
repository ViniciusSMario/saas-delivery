<?php

namespace App\Support;

use App\Modules\Access\Models\User;

/**
 * Itens do menu lateral do painel (seções e telas), na ordem em que aparecem. A busca
 * rápida (Ctrl+K) usa a mesma lista, com as palavras de "keywords" para achar a tela por
 * outro nome ("balcão" acha o PDV).
 *
 * can: permissão (ou lista: basta uma); feature: recurso do plano; match: rotas que deixam o
 * item marcado.
 */
final class PanelMenu
{
    /** @return list<array<string, mixed>> */
    public static function items(): array
    {
        return [
            ['route' => 'dashboard', 'label' => 'Início', 'icon' => 'home', 'can' => null, 'keywords' => 'início painel resumo hoje vendas'],
            ['section' => 'Atendimento', 'can' => ['orders.view', 'pos.use', 'tables.manage', 'cash.operate', 'deliveries.operate']],
            ['route' => 'orders.board', 'label' => 'Painel de pedidos', 'icon' => 'flame', 'can' => 'orders.view', 'match' => ['orders.board', 'orders.show'], 'keywords' => 'cozinha quadro kds preparo aceitar'],
            ['route' => 'tables.map', 'label' => 'Mesas', 'icon' => 'table', 'can' => 'tables.manage', 'match' => ['tables.map', 'tables.command'], 'keywords' => 'salão comanda garçom'],
            ['route' => 'orders.counter', 'label' => 'PDV', 'icon' => 'pos', 'can' => 'pos.use', 'keywords' => 'balcão venda telefone novo pedido caixa rápido'],
            ['route' => 'delivery.board', 'label' => 'Entregas', 'icon' => 'truck', 'can' => 'deliveries.operate', 'match' => ['delivery.*'], 'keywords' => 'entregador motoboy rota'],
            ['route' => 'cash.current', 'label' => 'Caixa', 'icon' => 'wallet', 'can' => 'cash.operate', 'match' => ['cash.*'], 'keywords' => 'abrir fechar sangria suprimento dinheiro'],
            ['route' => 'orders.index', 'label' => 'Histórico', 'icon' => 'clock', 'can' => 'orders.view', 'match' => ['orders.index'], 'keywords' => 'pedidos antigos buscar pedido'],
            ['route' => 'printing.station', 'label' => 'Estação de impressão', 'icon' => 'printer', 'can' => 'orders.view', 'keywords' => 'impressora cupom fila'],
            ['section' => 'Cardápio', 'can' => 'menu.manage'],
            ['route' => 'menu.products.index', 'label' => 'Produtos', 'icon' => 'book', 'can' => 'menu.manage', 'keywords' => 'cardápio itens preço esgotado'],
            ['route' => 'menu.categories', 'label' => 'Categorias', 'icon' => 'tag', 'can' => 'menu.manage'],
            ['route' => 'menu.addon-groups.index', 'label' => 'Adicionais', 'icon' => 'plus-circle', 'can' => 'menu.manage', 'keywords' => 'complementos opcionais borda sabores'],
            ['route' => 'menu.combos.index', 'label' => 'Combos', 'icon' => 'gift', 'can' => 'menu.manage'],
            ['route' => 'menu.coupons', 'label' => 'Cupons', 'icon' => 'ticket', 'can' => 'menu.manage', 'keywords' => 'desconto promoção código'],
            ['route' => 'menu.loyalty', 'label' => 'Fidelidade', 'icon' => 'star', 'can' => 'menu.manage', 'keywords' => 'cartão selos prêmio'],
            ['section' => 'Gestão', 'can' => ['customers.manage', 'finance.view', 'reports.view']],
            ['route' => 'customers.index', 'label' => 'Clientes', 'icon' => 'users', 'can' => 'customers.manage', 'match' => ['customers.index', 'customers.show']],
            ['route' => 'customers.winback', 'label' => 'Clientes sumidos', 'icon' => 'message', 'can' => 'customers.manage', 'keywords' => 'reativar inativos voltar'],
            ['route' => 'finance.index', 'label' => 'Financeiro', 'icon' => 'trending', 'can' => 'finance.view', 'match' => ['finance.*'], 'feature' => 'finance', 'keywords' => 'contas pagar receber lançamentos fluxo'],
            ['route' => 'reports.index', 'label' => 'Relatórios', 'icon' => 'chart', 'can' => 'reports.view', 'match' => ['reports.*'], 'keywords' => 'vendas faturamento ticket produtos mais vendidos'],
            ['section' => 'Estoque', 'can' => 'stock.manage', 'feature' => 'stock'],
            ['route' => 'stock.index', 'label' => 'Insumos', 'icon' => 'box', 'can' => 'stock.manage', 'match' => ['stock.index', 'stock.show'], 'feature' => 'stock', 'keywords' => 'estoque ingredientes compras'],
            ['route' => 'stock.recipes', 'label' => 'Fichas técnicas', 'icon' => 'file', 'can' => 'stock.manage', 'match' => ['stock.recipes', 'stock.recipe.*'], 'feature' => 'stock', 'keywords' => 'custo margem cmv'],
            ['route' => 'stock.consumption', 'label' => 'Consumo', 'icon' => 'activity', 'can' => 'stock.manage', 'match' => ['stock.consumption'], 'feature' => 'stock', 'keywords' => 'gasto ingredientes'],
            ['section' => 'Administração', 'can' => ['establishment.manage', 'settings.manage', 'users.view', 'roles.manage', 'audit.view', 'billing.manage', 'customers.manage', 'stock.manage', 'menu.manage']],
            ['route' => 'subscription', 'label' => 'Assinatura', 'icon' => 'card', 'can' => 'billing.manage', 'keywords' => 'plano fatura pagamento mensalidade'],
            ['route' => 'imports.index', 'label' => 'Importar dados', 'icon' => 'file', 'can' => ['customers.manage', 'settings.manage', 'stock.manage', 'menu.manage'], 'keywords' => 'planilha excel csv importar'],
            ['route' => 'data-exports', 'label' => 'Exportar dados', 'icon' => 'download', 'can' => 'billing.manage', 'keywords' => 'backup baixar'],
            ['route' => 'establishment.edit', 'label' => 'Estabelecimento', 'icon' => 'store', 'can' => 'establishment.manage', 'keywords' => 'loja endereço logo capa horário funcionamento'],
            ['route' => 'domains', 'label' => 'Domínio próprio', 'icon' => 'external', 'can' => 'establishment.manage'],
            ['route' => 'settings.edit', 'label' => 'Configurações', 'icon' => 'sliders', 'can' => 'settings.manage', 'keywords' => 'pagamento formas taxa entrega pedido mínimo tempo'],
            ['route' => 'printing.settings', 'label' => 'Impressoras', 'icon' => 'printer', 'can' => 'settings.manage', 'keywords' => 'setores cozinha bar'],
            ['route' => 'whatsapp.settings', 'label' => 'WhatsApp', 'icon' => 'phone', 'can' => 'settings.manage', 'keywords' => 'mensagens avisos cliente'],
            ['route' => 'delivery.zones', 'label' => 'Bairros e taxas', 'icon' => 'pin', 'can' => 'settings.manage', 'keywords' => 'frete taxa entrega bairro'],
            ['route' => 'users.index', 'label' => 'Usuários', 'icon' => 'user', 'can' => 'users.view', 'keywords' => 'equipe funcionários convidar'],
            ['route' => 'roles.index', 'label' => 'Perfis e permissões', 'icon' => 'shield', 'can' => 'roles.manage', 'keywords' => 'acesso permissão cargo'],
            ['route' => 'audit.index', 'label' => 'Auditoria', 'icon' => 'list', 'can' => 'audit.view', 'keywords' => 'registro histórico quem mexeu'],
        ];
    }

    /** O usuário vê o item (ou a seção). */
    public static function allows(User $user, array $item): bool
    {
        return ! $item['can'] || $user->canAny((array) $item['can']);
    }

    /** Recurso fora do plano: o dono vê o item com cadeado, levando à tela de assinatura. */
    public static function locked(User $user, array $item): bool
    {
        return isset($item['feature']) && ! tenant()->hasFeature($item['feature']) && $user->can('billing.manage');
    }

    /**
     * Telas que o usuário pode abrir, para a busca rápida.
     *
     * @return list<array{label: string, section: string, url: string, icon: string, keywords: string}>
     */
    public static function pagesFor(User $user): array
    {
        $pages = [];
        $section = '';

        foreach (self::items() as $item) {
            if (isset($item['section'])) {
                $section = $item['section'];

                continue;
            }

            if (! self::allows($user, $item) || (isset($item['feature']) && ! tenant()->hasFeature($item['feature']))) {
                continue;
            }

            $pages[] = [
                'label' => $item['label'],
                'section' => $section,
                'url' => route($item['route']),
                'icon' => $item['icon'],
                'keywords' => $item['keywords'] ?? '',
            ];
        }

        return $pages;
    }

    /**
     * Atalhos para criar algo, no topo da busca rápida.
     *
     * @return list<array{label: string, section: string, url: string, icon: string, keywords: string, external?: bool}>
     */
    public static function actionsFor(User $user): array
    {
        $actions = [
            ['can' => 'pos.use', 'label' => 'Nova venda no balcão', 'route' => 'orders.counter', 'icon' => 'plus', 'keywords' => 'pdv pedido telefone'],
            ['can' => 'menu.manage', 'label' => 'Novo produto', 'route' => 'menu.products.create', 'icon' => 'plus', 'keywords' => 'cadastrar item cardápio'],
            ['can' => 'menu.manage', 'label' => 'Importar cardápio por planilha', 'route' => 'menu.import', 'icon' => 'file', 'keywords' => 'excel csv produtos'],
            ['can' => 'finance.manage', 'feature' => 'finance', 'label' => 'Novo lançamento financeiro', 'route' => 'finance.create', 'icon' => 'plus', 'keywords' => 'conta pagar receber despesa'],
            ['can' => 'users.manage', 'label' => 'Convidar usuário', 'route' => 'users.create', 'icon' => 'user', 'keywords' => 'equipe funcionário novo'],
        ];

        $list = [];

        foreach ($actions as $action) {
            if (! $user->can($action['can']) || (isset($action['feature']) && ! tenant()->hasFeature($action['feature']))) {
                continue;
            }

            $list[] = ['label' => $action['label'], 'section' => 'Ações', 'url' => route($action['route']), 'icon' => $action['icon'], 'keywords' => $action['keywords']];
        }

        $list[] = ['label' => 'Ver o cardápio online', 'section' => 'Ações', 'url' => url('/cardapio'), 'icon' => 'external', 'keywords' => 'link cliente site', 'external' => true];

        return $list;
    }
}
