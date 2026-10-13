<?php

namespace App\Modules\Imports\Models;

use App\Tenancy\TenantModel;

/** Registro criado ou alterado por uma importação, com os valores de antes (para desfazer). */
class DataImportItem extends TenantModel
{
    public $timestamps = false;

    protected $fillable = ['data_import_id', 'subject_type', 'subject_id', 'action', 'before'];

    protected function casts(): array
    {
        return ['before' => 'array'];
    }
}
