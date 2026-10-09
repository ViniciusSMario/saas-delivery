<?php

use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Establishment\OpeningSchedule;
use Carbon\CarbonImmutable;

function schedule(array $days): OpeningSchedule
{
    return new OpeningSchedule(collect($days)->map(fn ($hours, $weekday) => new OpeningHour([
        'weekday' => $weekday,
        'is_closed' => $hours === null,
        'opens_at' => $hours[0] ?? null,
        'closes_at' => $hours[1] ?? null,
    ])));
}

// 2026-10-09 é sexta-feira (5).
it('abre e fecha no mesmo dia', function (string $moment, bool $open) {
    $s = schedule([5 => ['11:00', '15:00']]);

    expect($s->isOpenAt(CarbonImmutable::parse($moment)))->toBe($open);
})->with([
    ['2026-10-09 10:59', false],
    ['2026-10-09 11:00', true],
    ['2026-10-09 14:59', true],
    ['2026-10-09 15:00', false],
]);

it('atravessa a meia-noite (sexta 18h às 2h de sábado)', function (string $moment, bool $open) {
    $s = schedule([5 => ['18:00', '02:00'], 6 => null]);

    expect($s->isOpenAt(CarbonImmutable::parse($moment)))->toBe($open);
})->with([
    ['2026-10-09 17:59', false],
    ['2026-10-09 23:30', true],
    ['2026-10-10 01:59', true], // sábado de madrugada, ainda o turno de sexta
    ['2026-10-10 02:00', false],
    ['2026-10-10 20:00', false], // sábado fechado
]);

it('dia fechado ou sem cadastro fica fechado', function () {
    $s = schedule([5 => null]);

    expect($s->isOpenAt(CarbonImmutable::parse('2026-10-09 12:00')))->toBeFalse()
        ->and($s->isOpenAt(CarbonImmutable::parse('2026-10-11 12:00')))->toBeFalse()
        ->and($s->todayLabel(CarbonImmutable::parse('2026-10-09 12:00')))->toBe('Fechado hoje');
});
