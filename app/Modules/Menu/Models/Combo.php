<?php

namespace App\Modules\Menu\Models;

use App\Support\Casts\MoneyCast;
use App\Support\Money;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Combo extends TenantModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'price_cents', 'image_path', 'is_active', 'position'];

    protected $attributes = ['is_active' => true, 'position' => 0];

    protected function casts(): array
    {
        return ['price_cents' => MoneyCast::class, 'is_active' => 'boolean', 'position' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ComboItem::class)->orderBy('id');
    }

    /** Soma dos produtos avulsos (para mostrar a economia). */
    public function regularPrice(): Money
    {
        return $this->items->reduce(
            fn (Money $total, ComboItem $item) => $total->add(($item->product?->price_cents ?? Money::zero())->multiply($item->quantity)),
            Money::zero(),
        );
    }

    public function savings(): Money
    {
        $diff = $this->regularPrice()->subtract($this->price_cents);

        return $diff->cents > 0 ? $diff : Money::zero();
    }

    /** Disponível só se todos os produtos do combo estão ativos e não esgotados. */
    public function isAvailable(): bool
    {
        return $this->items->every(fn (ComboItem $item) => $item->product
            && $item->product->is_active
            && $item->product->is_available
            && ! $item->product->trashed());
    }
}
