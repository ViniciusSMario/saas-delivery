<?php

namespace App\Modules\Customers\Models;

use App\Modules\Access\Models\User;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cliente sumido chamado de volta pelo WhatsApp (quem chamou e com qual cupom). */
class CustomerWinback extends TenantModel
{
    public const UPDATED_AT = null;

    protected $fillable = ['customer_id', 'user_id', 'coupon_code'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
