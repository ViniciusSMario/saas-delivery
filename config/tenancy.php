<?php

return [

    // Hosts do site/cadastro: nunca são resolvidos como tenant.
    'central_domains' => array_values(array_filter(array_map(
        'trim', explode(',', (string) env('TENANCY_CENTRAL_DOMAINS', 'localhost'))
    ))),

    // Admin da plataforma (equipe do SaaS). Nunca é resolvido como tenant.
    'platform_domain' => env('TENANCY_PLATFORM_DOMAIN', 'admin.localhost'),

    // Cada tenant ganha <slug>.<base_domain> no provisionamento.
    'base_domain' => env('TENANCY_BASE_DOMAIN', 'localhost'),

    // Ordem em que o tenant é procurado. Novo modo = nova classe aqui.
    'resolvers' => [
        App\Tenancy\Resolvers\DomainResolver::class,
        App\Tenancy\Resolvers\PathResolver::class,
    ],

    'migrations_path' => 'database/migrations/tenant',

    'seeder' => Database\Seeders\Tenant\TenantDatabaseSeeder::class,

    // Role do PostgreSQL por tenant (SET ROLE): preparado, desligado no MVP.
    'database_roles' => false,

];
