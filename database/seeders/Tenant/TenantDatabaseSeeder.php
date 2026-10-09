<?php

namespace Database\Seeders\Tenant;

use App\Modules\Access\DefaultRoles;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Orders\Models\PaymentMethod;
use App\Modules\Stock\Models\Unit;
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
            'accepts_online_orders' => true,
            'accepts_delivery' => true,
            'accepts_pickup' => true,
            'min_order_cents' => 0,
            'delivery_fee_cents' => 0,
            'service_fee_enabled' => true,
            'service_fee_percent' => 10,
            'stock_auto_sold_out' => false,
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        DefaultRoles::seed();
        OpeningHour::seedDefaults();
        PaymentMethod::seedDefaults();
        CashRegister::seedDefault();
        Unit::seedDefaults();
        FinancialAccount::seedDefaults();
        FinancialCategory::seedDefaults();
    }
}
