<?php

namespace App\Modules\Establishment\Models;

use App\Tenancy\TenantModel;

/** Dados cadastrais do estabelecimento: uma única linha por schema. */
class Establishment extends TenantModel
{
    protected $table = 'establishment';

    protected $fillable = [
        'trade_name', 'legal_name', 'document', 'phone', 'email', 'zip_code', 'street',
        'number', 'complement', 'district', 'city', 'state', 'logo_path',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? new static(['trade_name' => tenant()?->name ?? '']);
    }

    public function address(): string
    {
        $line = collect([$this->street, $this->number])->filter()->implode(', ');
        $city = collect([$this->district, collect([$this->city, $this->state])->filter()->implode('/')])->filter()->implode(' · ');

        return collect([$line, $this->complement, $city])->filter()->implode(' · ');
    }
}
