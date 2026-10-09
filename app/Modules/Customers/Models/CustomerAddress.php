<?php

namespace App\Modules\Customers\Models;

use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends TenantModel
{
    protected $fillable = [
        'customer_id', 'zip_code', 'street', 'number', 'complement', 'district', 'city', 'reference', 'last_used_at',
    ];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
