<?php

namespace App\Modules\Messaging\Jobs;

use App\Modules\Messaging\Models\WhatsAppMessage;
use App\Modules\Messaging\WhatsApp;
use App\Tenancy\Jobs\TenantAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, TenantAware;

    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(public readonly int $messageId)
    {
    }

    public function handle(WhatsApp $whatsapp): void
    {
        $message = WhatsAppMessage::find($this->messageId);

        if (! $message || $message->status === 'sent') {
            return;
        }

        try {
            $id = $whatsapp->provider()->send($message->phone, $message->body);
        } catch (Throwable $e) {
            // Fila síncrona (ou envio direto) não tem nova tentativa: já é a última.
            $last = ! $this->job || $this->job->getConnectionName() === 'sync' || $this->attempts() >= $this->tries;
            $message->update(['status' => $last ? 'failed' : 'queued', 'error' => mb_substr($e->getMessage(), 0, 500)]);

            if (! $last) {
                throw $e;
            }

            return;
        }

        $message->update(['status' => 'sent', 'provider_message_id' => $id, 'sent_at' => now(), 'error' => null]);
    }
}
