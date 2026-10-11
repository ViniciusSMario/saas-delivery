<?php

namespace App\Modules\Push\Jobs;

use App\Modules\Push\Models\OrderPushSubscription;
use App\Modules\Push\PushTransport;
use App\Tenancy\Jobs\TenantAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Entrega o aviso de andamento nos celulares do cliente inscritos no pedido. */
class SendOrderPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, TenantAware;

    public int $tries = 3;

    public int $backoff = 5;

    /**
     * @param  array{title: string, body: string, tag: string}  $payload
     * @param  bool  $final  pedido terminou: depois deste aviso as inscrições são apagadas
     */
    public function __construct(public int $orderId, public array $payload, public bool $final = false)
    {
        $this->afterCommit();
    }

    public function handle(PushTransport $transport): void
    {
        $expired = [];

        // Cada celular abre a página do pedido no endereço em que se inscreveu (subdomínio ou /cardapio/{slug}).
        foreach (OrderPushSubscription::where('order_id', $this->orderId)->get() as $subscription) {
            $expired = [...$expired, ...$transport->send(collect([$subscription]), $this->payload + [
                'url' => $subscription->page_url,
                'icon' => $subscription->icon_url,
                'sent_at' => now()->getTimestampMs(),
            ])];
        }

        $query = OrderPushSubscription::where('order_id', $this->orderId);

        if (! $this->final) {
            $query->whereIn('endpoint', $expired);
        }

        $query->delete();
    }
}
