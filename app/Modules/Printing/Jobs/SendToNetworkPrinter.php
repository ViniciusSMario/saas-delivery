<?php

namespace App\Modules\Printing\Jobs;

use App\Modules\Printing\EscPos;
use App\Modules\Printing\Models\PrintJob;
use App\Tenancy\Jobs\TenantAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;
use Throwable;

/**
 * Manda o cupom direto para uma impressora térmica na rede (porta RAW 9100).
 * Só funciona quando o servidor enxerga a rede do restaurante (instalação local).
 */
class SendToNetworkPrinter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, TenantAware;

    public int $tries = 3;

    public array $backoff = [5, 30];

    public function __construct(public readonly int $printJobId)
    {
    }

    public function handle(): void
    {
        $job = PrintJob::with('station')->find($this->printJobId);

        if (! $job || $job->status === 'printed' || ! $job->station?->isNetwork()) {
            return;
        }

        $job->increment('attempts');

        try {
            $socket = @fsockopen($job->station->host, $job->station->port, $errno, $error, (float) config('printing.timeout'));

            if (! $socket) {
                throw new RuntimeException("Impressora {$job->station->host}:{$job->station->port} não respondeu ({$error}).");
            }

            fwrite($socket, EscPos::encode($job->lines, $job->station->copies));
            fclose($socket);
        } catch (Throwable $e) {
            $job->update(['status' => $this->attempts() >= $this->tries ? 'failed' : 'pending', 'error' => $e->getMessage()]);

            throw $e;
        }

        $job->update(['status' => 'printed', 'printed_at' => now(), 'error' => null]);
    }
}
