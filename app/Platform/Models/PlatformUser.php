<?php

namespace App\Platform\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** Equipe do SaaS (guard "platform", a ser ligado na fase 2). */
class PlatformUser extends Authenticatable
{
    protected $connection = 'central';

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
        ];
    }
}
