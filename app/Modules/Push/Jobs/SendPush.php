<?php

namespace App\Modules\Push\Jobs;

use App\Modules\Push\Models\PushSubscription;
use App\Modules\Push\PushTransport;
use App\Tenancy\Jobs\TenantAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Entrega um aviso em todos os celulares inscritos dos usuários indicados. */
class SendPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, TenantAware;

    public int $tries = 3;

    public int $backoff = 5;

    /**
     * @param  list<int>  $userIds
     * @param  array{title: string, body: string, url: string, tag: string, sticky?: bool}  $payload  sticky: fica na tela até a pessoa tocar (computador)
     */
    public function __construct(public array $userIds, public array $payload)
    {
        $this->afterCommit();
    }

    public function handle(PushTransport $transport): void
    {
        $subscriptions = PushSubscription::whereIn('user_id', $this->userIds)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $expired = $transport->send($subscriptions, $this->payload + ['sent_at' => now()->getTimestampMs()]);

        // Celular que desinstalou o app ou revogou a permissão: some da lista.
        if ($expired) {
            PushSubscription::whereIn('endpoint', $expired)->delete();
        }

        PushSubscription::whereIn('id', $subscriptions->pluck('id'))->whereNotIn('endpoint', $expired)->update(['last_used_at' => now()]);
    }
}
