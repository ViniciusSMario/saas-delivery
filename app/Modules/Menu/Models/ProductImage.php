<?php

namespace App\Modules\Menu\Models;

use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto de produto no disco do tenant. O caminho é gerado pelo sistema. */
class ProductImage extends TenantModel
{
    protected $fillable = ['product_id', 'path', 'position'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
