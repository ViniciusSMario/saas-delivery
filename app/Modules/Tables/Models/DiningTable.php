<?php

namespace App\Modules\Tables\Models;

use App\Modules\Tables\CommandStatus;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Mesa do salão (tabela "tables"). */
class DiningTable extends TenantModel
{
    protected $table = 'tables';

    protected $fillable = ['name', 'seats', 'area', 'is_active', 'position'];

    protected $attributes = ['is_active' => true, 'seats' => 4, 'position' => 0];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'seats' => 'integer', 'position' => 'integer'];
    }

    public function commands(): HasMany
    {
        return $this->hasMany(Command::class, 'table_id');
    }

    /** Comanda em aberto (no máximo uma, garantido por índice único). */
    public function openCommand(): HasOne
    {
        return $this->hasOne(Command::class, 'table_id')->whereIn('status', CommandStatus::openValues());
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderByRaw("lpad(name, 10, '0')");
    }
}
