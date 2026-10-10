<?php

namespace App\Http\Controllers\Infra;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/** /saude: para o monitor de disponibilidade (UptimeRobot, Better Stack...) e o deploy. */
class HealthController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::connection('central')->select('select 1')),
            'redis' => $this->check(fn () => Redis::connection()->ping()),
            'storage' => $this->check(fn () => is_writable(storage_path('framework/cache')) ?: throw new \RuntimeException('storage sem escrita')),
        ];

        $ok = ! in_array(false, $checks, true);

        return response()->json(['status' => $ok ? 'ok' : 'erro', 'checks' => $checks], $ok ? 200 : 503);
    }

    private function check(callable $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
