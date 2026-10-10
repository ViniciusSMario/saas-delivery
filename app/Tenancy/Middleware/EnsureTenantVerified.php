<?php

namespace App\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cardápio público só depois que o dono confirma o e-mail (cadastro self-service).
 * O painel continua liberado: dá para montar o cardápio enquanto isso.
 */
class EnsureTenantVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant && ! $tenant->email_verified_at) {
            return response()->view('tenancy.unverified', ['tenant' => $tenant], 403);
        }

        return $next($request);
    }
}
