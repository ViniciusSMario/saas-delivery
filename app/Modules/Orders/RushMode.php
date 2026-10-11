<?php

namespace App\Modules\Orders;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use App\Modules\Establishment\Models\Setting;
use Carbon\CarbonImmutable;

/**
 * Hora do sufoco: no pico, a equipe aumenta a previsão que o cliente vê ou pausa os pedidos
 * online por alguns minutos. As duas coisas se desfazem sozinhas (ninguém esquece ligado).
 */
class RushMode
{
    public const DELAY_SETTING = 'rush_delay';

    public const PAUSE_SETTING = 'online_paused_until';

    /** Minutos a mais na previsão. */
    public const DELAY_OPTIONS = [15, 30, 45];

    /** Minutos de pausa dos pedidos online. */
    public const PAUSE_OPTIONS = [15, 30, 60];

    /** O tempo extra volta ao normal sozinho depois deste tempo. */
    public const DELAY_HOURS = 3;

    /** Minutos somados à previsão agora (0 = movimento normal). */
    public static function extraMinutes(): int
    {
        $delay = Setting::read(self::DELAY_SETTING);

        if (! is_array($delay) || ! self::future($delay['until'] ?? null)) {
            return 0;
        }

        return max(0, (int) ($delay['minutes'] ?? 0));
    }

    /** Até quando vale o tempo extra (null = movimento normal). */
    public static function delayUntil(): ?CarbonImmutable
    {
        $delay = Setting::read(self::DELAY_SETTING);

        return is_array($delay) && (int) ($delay['minutes'] ?? 0) > 0 ? self::future($delay['until'] ?? null) : null;
    }

    /** Até quando os pedidos online estão pausados (null = recebendo). */
    public static function pausedUntil(): ?CarbonImmutable
    {
        return self::future(Setting::read(self::PAUSE_SETTING));
    }

    public static function delay(int $minutes, ?User $actor = null): void
    {
        abort_unless(in_array($minutes, self::DELAY_OPTIONS, true), 422);

        $until = now()->addHours(self::DELAY_HOURS);
        Setting::write(self::DELAY_SETTING, ['minutes' => $minutes, 'until' => $until->toIso8601String()]);
        Audit::record('rush.delay', changes: ['minutos_a_mais' => $minutes], actor: $actor);
    }

    public static function clearDelay(?User $actor = null): void
    {
        if (self::extraMinutes() === 0) {
            return;
        }

        Setting::write(self::DELAY_SETTING, null);
        Audit::record('rush.delay_cleared', actor: $actor);
    }

    public static function pause(int $minutes, ?User $actor = null): void
    {
        abort_unless(in_array($minutes, self::PAUSE_OPTIONS, true), 422);

        Setting::write(self::PAUSE_SETTING, now()->addMinutes($minutes)->toIso8601String());
        Audit::record('rush.paused', changes: ['minutos' => $minutes], actor: $actor);
    }

    public static function resume(?User $actor = null): void
    {
        if (! self::pausedUntil()) {
            return;
        }

        Setting::write(self::PAUSE_SETTING, null);
        Audit::record('rush.resumed', actor: $actor);
    }

    private static function future(mixed $moment): ?CarbonImmutable
    {
        if (! is_string($moment) || $moment === '') {
            return null;
        }

        $moment = CarbonImmutable::parse($moment);

        return $moment->isFuture() ? $moment : null;
    }
}
