<?php

namespace App\Modules\Menu\Models;

use App\Modules\Printing\Models\PrintStation;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends TenantModel
{
    protected $fillable = ['name', 'description', 'position', 'is_active', 'print_station_id'];

    /** Setor de produção que prepara os itens desta categoria (impressora). */
    public function printStation(): BelongsTo
    {
        return $this->belongsTo(PrintStation::class);
    }

    protected $attributes = ['is_active' => true, 'position' => 0];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'position' => 'integer'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('position')->orderBy('name');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('name');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
