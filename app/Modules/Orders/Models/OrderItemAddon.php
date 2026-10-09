<?php

namespace App\Modules\Orders\Models;

use App\Support\Casts\MoneyCast;
use App\Tenancy\TenantModel;

class OrderItemAddon extends TenantModel
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['unit_price_cents' => MoneyCast::class];
    }
}
