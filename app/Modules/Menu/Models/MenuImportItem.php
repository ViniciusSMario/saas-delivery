<?php

namespace App\Modules\Menu\Models;

use App\Tenancy\TenantModel;

/** Registro criado ou alterado por uma importação, com os valores de antes. */
class MenuImportItem extends TenantModel
{
    public $timestamps = false;

    protected $fillable = ['menu_import_id', 'subject_type', 'subject_id', 'action', 'before'];

    protected function casts(): array
    {
        return ['before' => 'array'];
    }
}
