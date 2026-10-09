<?php

namespace App\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $connection = 'central';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'features' => 'array',
        ];
    }
}
