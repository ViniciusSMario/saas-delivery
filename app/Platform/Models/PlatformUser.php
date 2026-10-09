<?php

namespace App\Platform\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** Equipe do SaaS (guard "platform"). 2FA obrigatório no login. */
class PlatformUser extends Authenticatable
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_SUPPORT = 'support';

    protected $connection = 'central';

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (PlatformUser $user) => $user->email = mb_strtolower(trim($user->email)));
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }
}
