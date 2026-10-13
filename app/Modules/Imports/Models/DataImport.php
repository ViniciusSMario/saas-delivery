<?php

namespace App\Modules\Imports\Models;

use App\Modules\Access\Models\User;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Importação por planilha (cardápio, clientes, bairros, estoque, fichas): histórico e desfazer. */
class DataImport extends TenantModel
{
    public const UPDATED_AT = null;

    protected $fillable = ['kind', 'user_id', 'file_name', 'photos_file_name', 'mode', 'status', 'summary', 'undone_at', 'undone_by'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'undone_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DataImportItem::class);
    }

    public function scopeOfKind(Builder $query, string $kind): void
    {
        $query->where('kind', $kind);
    }

    /** Só a última importação ainda aplicada de cada tipo pode ser desfeita (as anteriores podem ter sido mudadas por ela). */
    public function canUndo(): bool
    {
        return $this->status === 'applied'
            && ! self::ofKind($this->kind)->where('status', 'applied')->where('id', '>', $this->id)->exists();
    }
}
