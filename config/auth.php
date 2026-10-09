<?php

use App\Modules\Access\Models\User;
use App\Platform\Models\PlatformUser;

return [

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        // Usuários do estabelecimento: tabela users do schema do tenant.
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // Equipe do SaaS: public.platform_users, só no domínio do admin.
        'platform' => [
            'driver' => 'session',
            'provider' => 'platform_users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],

        'platform_users' => [
            'driver' => 'eloquent',
            'model' => PlatformUser::class,
        ],
    ],

    'passwords' => [
        // Tokens em password_reset_tokens do schema do tenant (conexão padrão).
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60 * 24,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
