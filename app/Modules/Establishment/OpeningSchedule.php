<?php

namespace App\Modules\Establishment;

use App\Modules\Establishment\Models\OpeningHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Responde "está aberto agora?" a partir dos horários por dia da semana.
 * Fechamento antes da abertura (ex.: 18:00 às 02:00) vira a madrugada do dia seguinte.
 */
class OpeningSchedule
{
    /** @param  Collection<int, OpeningHour>  $hours  indexado por weekday */
    public function __construct(private readonly Collection $hours)
    {
    }

    public static function load(): self
    {
        return new self(OpeningHour::all()->keyBy('weekday'));
    }

    public function isOpenAt(CarbonImmutable $moment): bool
    {
        $time = $moment->format('H:i');

        // Turno de hoje.
        $today = $this->hours->get($moment->dayOfWeek);

        if ($today && ! $today->is_closed && $today->opens_at && $today->closes_at) {
            [$opens, $closes] = [substr($today->opens_at, 0, 5), substr($today->closes_at, 0, 5)];

            if ($closes > $opens ? ($time >= $opens && $time < $closes) : $time >= $opens) {
                return true;
            }
        }

        // Turno de ontem que atravessou a meia-noite.
        $yesterday = $this->hours->get($moment->subDay()->dayOfWeek);

        if ($yesterday && ! $yesterday->is_closed && $yesterday->opens_at && $yesterday->closes_at) {
            [$opens, $closes] = [substr($yesterday->opens_at, 0, 5), substr($yesterday->closes_at, 0, 5)];

            if ($closes <= $opens && $time < $closes) {
                return true;
            }
        }

        return false;
    }

    /** Texto curto para hoje: "Hoje: 18:00 às 02:00" ou "Fechado hoje". */
    public function todayLabel(CarbonImmutable $moment): string
    {
        $today = $this->hours->get($moment->dayOfWeek);

        if (! $today || $today->is_closed || ! $today->opens_at) {
            return 'Fechado hoje';
        }

        return 'Hoje: '.substr($today->opens_at, 0, 5).' às '.substr($today->closes_at, 0, 5);
    }

    /** @return list<array{label: string, hours: string}> semana começando na segunda */
    public function week(): array
    {
        $rows = [];

        foreach ([1, 2, 3, 4, 5, 6, 0] as $weekday) {
            $hour = $this->hours->get($weekday);
            $rows[] = [
                'label' => OpeningHour::WEEKDAYS[$weekday],
                'hours' => (! $hour || $hour->is_closed || ! $hour->opens_at)
                    ? 'Fechado'
                    : substr($hour->opens_at, 0, 5).' às '.substr($hour->closes_at, 0, 5),
            ];
        }

        return $rows;
    }
}
