<?php

namespace App\Modules\Messaging\Models;

use App\Modules\Orders\Models\Order;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property string $status queued | sent | failed */
class WhatsAppMessage extends TenantModel
{
    protected $table = 'whatsapp_messages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
