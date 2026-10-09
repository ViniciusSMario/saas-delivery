<?php

namespace Database\Seeders\Tenant;

use App\Modules\Access\DefaultRoles;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Establishment\Models\Setting;
use Illuminate\Database\Seeder;

/** Roda dentro do schema do tenant (conexão "tenant"). Idempotente. */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'welcome_message' => 'Bem-vindo!',
            'currency' => 'BRL',
            'timezone' => 'America/Sao_Paulo',
            'accepts_delivery' => true,
            'accepts_pickup' => true,
            'min_order_cents' => 0,
            'delivery_fee_cents' => 0,
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        DefaultRoles::seed();
        OpeningHour::seedDefaults();
    }
}
