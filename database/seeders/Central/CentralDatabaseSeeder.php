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
    ];

    public function run(): void
    {
        DB::connection('central')->table('reserved_slugs')->insertOrIgnore(
            array_map(fn (string $slug) => ['slug' => $slug], self::RESERVED_SLUGS)
        );

        Plan::firstOrCreate(['name' => 'Básico'], [
            'price_cents' => 9900,
            'limits' => ['users' => 5, 'products' => 300, 'orders_per_month' => 3000],
            'features' => ['menu' => true, 'pos' => true, 'stock' => false],
        ]);
    }
}
