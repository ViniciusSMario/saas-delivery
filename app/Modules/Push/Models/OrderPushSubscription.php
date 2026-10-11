<?php

namespace App\Modules\Push\Models;

use App\Modules\Orders\Models\Order;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Celular do cliente inscrito para saber do andamento de um pedido. Apagada quando o pedido termina. */
class OrderPushSubscription extends TenantModel
{
    public const MAX_PER_ORDER = 5;

    protected $fillable = ['order_id', 'endpoint', 'public_key', 'auth_token', 'content_encoding', 'page_url', 'icon_url'];

    protected $hidden = ['public_key', 'auth_token'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
