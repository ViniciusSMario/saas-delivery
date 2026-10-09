<?php

namespace App\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * No host de um tenant, auth() é sempre o guard dos usuários do estabelecimento.
 * Um usuário de outro guard (equipe do SaaS) nunca conta como autenticado aqui.
 */
class UseTenantGuard
{
    public const GUARD = 'web';

    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse(self::GUARD);

        return $next($request);
    }
}
