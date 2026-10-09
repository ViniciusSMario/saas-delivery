<?php

namespace App\Modules\Stock\Models;

use App\Modules\Access\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Stock\MovementType;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends TenantModel
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'unit_cost_cents' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
