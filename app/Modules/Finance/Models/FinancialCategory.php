<?php

namespace App\Modules\Finance\Models;

use App\Tenancy\TenantModel;

class FinancialCategory extends TenantModel
{
    public $timestamps = false;

    protected $fillable = ['name', 'kind'];

    public const DEFAULTS = [
        'payable' => ['Fornecedores', 'Aluguel', 'Salários', 'Energia', 'Água', 'Gás', 'Internet e telefone', 'Impostos', 'Manutenção', 'Marketing', 'Taxas de cartão', 'Outros'],
        'receivable' => ['Vendas', 'Eventos', 'Outros'],
    ];

    public static function seedDefaults(): void
    {
        foreach (self::DEFAULTS as $kind => $names) {
            foreach ($names as $name) {
                static::firstOrCreate(['name' => $name, 'kind' => $kind]);
            }
        }
    }
}
