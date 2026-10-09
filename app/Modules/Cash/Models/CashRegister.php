<?php

namespace App\Modules\Cash\Models;

use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends TenantModel
{
    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    public static function seedDefault(): void
    {
        if (! static::exists()) {
            static::create(['name' => 'Caixa 1']);
        }
    }
}
