<?php

namespace App\Modules\Access;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Permission as P;

/** Perfis criados em todo estabelecimento novo. Idempotente: pode rodar de novo. */
class DefaultRoles
{
    public const OWNER = 'dono';

    /** @return array<string, array{name: string, description: string, owner?: bool, permissions: list<P>}> */
    public static function definitions(): array
    {
        return [
            self::OWNER => [
                'name' => 'Dono',
                'description' => 'Acesso total, inclusive perfis e permissões.',
                'owner' => true,
                'permissions' => [],
            ],
            'gerente' => [
                'name' => 'Gerente',
                'description' => 'Opera e administra o dia a dia; não altera perfis.',
                'permissions' => array_values(array_filter(P::cases(), fn (P $p) => $p !== P::RolesManage)),
            ],
            'caixa' => [
                'name' => 'Caixa',
                'description' => 'PDV, caixa e pedidos de balcão.',
                'permissions' => [P::PosUse, P::CashOperate, P::OrdersView, P::OrdersManage, P::CustomersManage],
            ],
            'garcom' => [
                'name' => 'Garçom',
                'description' => 'Mesas, comandas e pedidos.',
                'permissions' => [P::TablesManage, P::OrdersView, P::OrdersManage],
            ],
            'cozinha' => [
                'name' => 'Cozinha',
                'description' => 'Painel de pedidos e status de preparo.',
                'permissions' => [P::OrdersView, P::OrdersManage],
            ],
            'entregador' => [
                'name' => 'Entregador',
                'description' => 'Consulta pedidos para entrega.',
                'permissions' => [P::OrdersView],
            ],
        ];
    }

    public static function seed(): void
    {
        foreach (self::definitions() as $slug => $definition) {
            $role = Role::firstOrCreate(['slug' => $slug], [
                'name' => $definition['name'],
                'description' => $definition['description'],
                'is_system' => true,
                'is_owner' => $definition['owner'] ?? false,
            ]);

            // Só preenche permissões de perfis recém-criados: não desfaz ajustes do dono.
            if ($role->wasRecentlyCreated && ! $role->is_owner) {
                $role->syncPermissions($definition['permissions']);
            }
        }
    }

    public static function owner(): Role
    {
        return Role::where('slug', self::OWNER)->firstOrFail();
    }
}
