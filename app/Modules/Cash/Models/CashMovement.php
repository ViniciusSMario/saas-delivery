<?php

namespace App\Modules\Cash\Models;

use App\Modules\Access\Models\User;
use App\Modules\Cash\MovementType;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PaymentMethod;
use App\Modules\Tables\Models\Command;
use App\Support\Casts\MoneyCast;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends TenantModel
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['type' => MovementType::class, 'amount_cents' => MoneyCast::class];
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class);
    }
}
