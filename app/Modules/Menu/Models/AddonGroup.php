<?php

namespace App\Modules\Menu\Models;

use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Grupo de adicionais/opções (ex.: "Escolha a borda", "Adicionais"). */
class AddonGroup extends TenantModel
{
    protected $fillable = ['name', 'min_choices', 'max_choices', 'is_active'];

    protected $attributes = ['is_active' => true, 'min_choices' => 0, 'max_choices' => 1];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'min_choices' => 'integer', 'max_choices' => 'integer'];
    }

    public function addons(): HasMany
    {
        return $this->hasMany(Addon::class)->orderBy('position')->orderBy('id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_addon_groups');
    }

    public function isRequired(): bool
    {
        return $this->min_choices > 0;
    }

    /** Ex.: "Escolha 1", "Escolha até 3", "Escolha de 1 a 2". */
    public function rule(): string
    {
        return match (true) {
            $this->min_choices === $this->max_choices => "Escolha {$this->max_choices}",
            $this->min_choices === 0 => "Escolha até {$this->max_choices}",
            default => "Escolha de {$this->min_choices} a {$this->max_choices}",
        };
    }
}
