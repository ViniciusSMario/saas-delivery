<?php

namespace App\Tenancy;

use App\Tenancy\Models\Tenant;

/**
 * URL absoluta no domínio primário de um tenant. Serve para links em e-mails
 * gerados fora de uma requisição (console, jobs), onde url() não conhece o host.
 */
class TenantUrl
{
    public static function to(Tenant $tenant, string $path = '/'): string
    {
        $appUrl = (string) config('app.url');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'http';
        $port = parse_url($appUrl, PHP_URL_PORT);

        return $scheme.'://'.$tenant->primaryDomain().($port ? ":{$port}" : '').'/'.ltrim($path, '/');
    }
}
