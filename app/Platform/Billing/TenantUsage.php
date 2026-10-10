<?php

namespace App\Platform\Billing;

use App\Modules\Access\Models\User;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Tables\Models\DiningTable;
use App\Tenancy\Models\Tenant;
use App\Tenancy\TenantContext;

/** Quanto o estabelecimento usa de cada limite de plano (lido do schema dele). */
class TenantUsage
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    /** @return array{users: int, tables: int, products: int, orders_per_month: int} */
    public function of(Tenant $tenant): array
    {
        $read = fn () => [
            'users' => User::query()->where('is_active', true)->count(),
            'tables' => DiningTable::query()->count(),
            'products' => Product::query()->count(),
            'orders_per_month' => Order::query()->where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        return $this->context->run($tenant, $read);
    }
}
