<?php

namespace App\Modules\Menu\Models;

use App\Support\Casts\MoneyCast;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Addon extends TenantModel
{
    protected $fillable = ['addon_group_id', 'name', 'price_cents', 'is_active', 'position'];

    protected $attributes = ['is_active' => true, 'price_cents' => 0, 'position' => 0];

    protected function casts(): array
    {
        return ['price_cents' => MoneyCast::class, 'is_active' => 'boolean', 'position' => 'integer'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AddonGroup::class, 'addon_group_id');
    }
}
