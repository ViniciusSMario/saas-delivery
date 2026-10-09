<?php

namespace App\Modules\Menu\Models;

use App\Support\Casts\MoneyCast;
use App\Support\Money;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property Money $price
 */
class Product extends TenantModel
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'code', 'name', 'description', 'price_cents',
        'is_active', 'is_available', 'is_featured', 'position',
    ];

    protected $attributes = ['is_active' => true, 'is_available' => true, 'is_featured' => false, 'position' => 0];

    protected function casts(): array
    {
        return [
            'price_cents' => MoneyCast::class,
            'is_active' => 'boolean',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function getPriceAttribute(): Money
    {
        return $this->price_cents;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    public function cover(): HasOne
    {
        return $this->hasOne(ProductImage::class)->ofMany(['position' => 'min', 'id' => 'min']);
    }

    public function addonGroups(): BelongsToMany
    {
        return $this->belongsToMany(AddonGroup::class, 'product_addon_groups')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Busca do PDV/admin: código exato ou parte do nome (índice trigram). */
    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';

        $query->where(fn (Builder $q) => $q
            ->where('name', 'ilike', $like)
            ->orWhereRaw('lower(code) = lower(?)', [$term]));
    }
}
