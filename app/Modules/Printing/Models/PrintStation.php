<?php

namespace App\Modules\Printing\Models;

use App\Modules\Menu\Models\Category;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Setor de produção com a sua impressora (cozinha, bar, expedição...). */
class PrintStation extends TenantModel
{
    protected $fillable = ['name', 'mode', 'host', 'port', 'columns', 'copies', 'prints_full_order', 'is_default', 'is_active'];

    protected $attributes = ['mode' => 'browser', 'port' => 9100, 'columns' => 48, 'copies' => 1, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'columns' => 'integer',
            'copies' => 'integer',
            'prints_full_order' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function isNetwork(): bool
    {
        return $this->mode === 'network';
    }
}
