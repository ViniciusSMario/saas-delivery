<?php

namespace Database\Seeders\Tenant;

use App\Modules\Establishment\Models\Setting;
use Illuminate\Database\Seeder;

/** Roda dentro do schema do tenant (conexão "tenant"). */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'welcome_message' => 'Bem-vindo!',
            'currency' => 'BRL',
            'timezone' => 'America/Sao_Paulo',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
