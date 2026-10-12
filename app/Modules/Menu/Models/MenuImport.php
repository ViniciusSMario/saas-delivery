<?php

namespace App\Modules\Menu\Models;

use App\Modules\Access\Models\User;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Importação do cardápio por planilha (histórico e desfazer). */
class MenuImport extends TenantModel
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'file_name', 'photos_file_name', 'mode', 'status', 'summary', 'undone_at', 'undone_by'];

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
        return $this->hasMany(MenuImportItem::class);
    }
}
