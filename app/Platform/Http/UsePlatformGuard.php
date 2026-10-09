<?php

namespace App\Platform\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * No domínio do admin, auth() fala com o guard "platform" (public.platform_users),
 * nunca com o guard "web", que dependeria de um tenant ativo.
 */
class UsePlatformGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('platform');

        return $next($request);
    }
}
