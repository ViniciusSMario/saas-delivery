<?php

namespace App\Modules\Orders\Models;

use App\Modules\Access\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends TenantModel
{
    public const UPDATED_AT = null;

    protected $table = 'order_status_history';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['from_status' => OrderStatus::class, 'to_status' => OrderStatus::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
