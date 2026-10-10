<?php

namespace Database\Seeders\Central;

use App\Platform\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CentralDatabaseSeeder extends Seeder
{
    public const RESERVED_SLUGS = [
        'www', 'admin', 'api', 'app', 'cardapio', 'login', 'cadastro', 'suporte',
        'ajuda', 'blog', 'mail', 'static', 'assets', 'cdn', 'status', 'dev', 'teste',
        'webhooks', 'faturas', 'planos',
    ];

    public function run(): void
    {
        DB::connection('central')->table('reserved_slugs')->insertOrIgnore(
            array_map(fn (string $slug) => ['slug' => $slug], self::RESERVED_SLUGS)
        );

        // Limite null = ilimitado. Recurso ausente = liberado.
        $plans = [
            'essencial' => [
                'name' => 'Essencial', 'price_cents' => 8990, 'sort' => 1,
                'description' => 'Cardápio online, pedidos, mesas, PDV e caixa para começar.',
                'limits' => ['users' => 3, 'tables' => 10, 'products' => 150, 'orders_per_month' => 1500],
                'features' => ['stock' => false, 'finance' => false],
            ],
            'profissional' => [
                'name' => 'Profissional', 'price_cents' => 14990, 'sort' => 2,
                'description' => 'Tudo do Essencial, com estoque, ficha técnica e financeiro.',
                'limits' => ['users' => 10, 'tables' => 30, 'products' => 500, 'orders_per_month' => 5000],
                'features' => ['stock' => true, 'finance' => true],
            ],
            'completo' => [
                'name' => 'Completo', 'price_cents' => 24990, 'sort' => 3,
                'description' => 'Sem limites de usuários, mesas, produtos ou pedidos.',
                'limits' => ['users' => null, 'tables' => null, 'products' => null, 'orders_per_month' => null],
                'features' => ['stock' => true, 'finance' => true],
            ],
        ];

        foreach ($plans as $slug => $data) {
            Plan::updateOrCreate(['slug' => $slug], $data + ['trial_days' => 14, 'is_public' => true, 'is_active' => true]);
        }
    }
}
