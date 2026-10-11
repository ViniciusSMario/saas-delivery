<?php

namespace App\Modules\Push;

use App\Modules\Push\Models\PushSubscription;
use Illuminate\Support\Collection;

/** Quem entrega o aviso ao serviço de push do celular. */
interface PushTransport
{
    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @param  array{title: string, body: string, url: string, tag: string}  $payload
     * @return list<string> endpoints que não existem mais (celular desinstalou ou revogou): serão apagados
     */
    public function send(Collection $subscriptions, array $payload): array;
}
