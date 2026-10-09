<?php

namespace App\Modules\Access;

/**
 * Permissões definidas no código. Perfis (dados do tenant) agrupam permissões;
 * Gate::before resolve cada uma pelo valor (ex.: can('users.manage')).
 */
enum Permission: string
{
    case EstablishmentManage = 'establishment.manage';
    case SettingsManage = 'settings.manage';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case AuditView = 'audit.view';

    case MenuManage = 'menu.manage';
    case OrdersView = 'orders.view';
    case OrdersManage = 'orders.manage';
    case OrdersCancel = 'orders.cancel';
    case OrdersDiscount = 'orders.discount';
    case TablesManage = 'tables.manage';
    case PosUse = 'pos.use';
    case CashOperate = 'cash.operate';
    case CashManage = 'cash.manage';
    case StockManage = 'stock.manage';
    case CustomersManage = 'customers.manage';
    case FinanceView = 'finance.view';
    case FinanceManage = 'finance.manage';
    case ReportsView = 'reports.view';

    public function label(): string
    {
        return match ($this) {
            self::EstablishmentManage => 'Editar dados do estabelecimento',
            self::SettingsManage => 'Alterar configurações',
            self::UsersView => 'Ver usuários',
            self::UsersManage => 'Criar e editar usuários',
            self::RolesManage => 'Gerenciar perfis e permissões',
            self::AuditView => 'Ver registro de auditoria',
            self::MenuManage => 'Gerenciar cardápio',
            self::OrdersView => 'Ver pedidos',
            self::OrdersManage => 'Criar e atualizar pedidos',
            self::OrdersCancel => 'Cancelar pedidos e itens',
            self::OrdersDiscount => 'Aplicar descontos',
            self::TablesManage => 'Gerenciar mesas e comandas',
            self::PosUse => 'Usar o PDV',
            self::CashOperate => 'Operar caixa (abrir, sangria, suprimento)',
            self::CashManage => 'Fechar e conferir caixa',
            self::StockManage => 'Gerenciar estoque',
            self::CustomersManage => 'Gerenciar clientes',
            self::FinanceView => 'Ver financeiro',
            self::FinanceManage => 'Lançar contas a pagar e receber',
            self::ReportsView => 'Ver relatórios e dashboard',
        };
    }

    public function group(): string
    {
        return match (explode('.', $this->value)[0]) {
            'establishment', 'settings' => 'Estabelecimento',
            'users', 'roles', 'audit' => 'Acesso',
            'menu' => 'Cardápio',
            'orders', 'tables', 'pos' => 'Pedidos e PDV',
            'cash', 'finance', 'reports' => 'Caixa e financeiro',
            default => 'Operação',
        };
    }

    /** @return array<string, list<self>> */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = $permission;
        }

        return $groups;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
