<?php

namespace App\Modules\Establishment\Models;

use App\Tenancy\TenantModel;

class OpeningHour extends TenantModel
{
    public const WEEKDAYS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

    protected $fillable = ['weekday', 'is_closed', 'opens_at', 'closes_at'];

    protected function casts(): array
    {
        return ['is_closed' => 'boolean', 'weekday' => 'integer'];
    }

    public function label(): string
    {
        return self::WEEKDAYS[$this->weekday];
    }

    public static function seedDefaults(): void
    {
        foreach (array_keys(self::WEEKDAYS) as $weekday) {
            static::firstOrCreate(['weekday' => $weekday], [
                'is_closed' => false,
                'opens_at' => '11:00',
                'closes_at' => '23:00',
            ]);
        }
    }
}
