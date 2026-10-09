<?php

namespace App\Modules\Access\Http;

use App\Modules\Access\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Usuário desativado perde o acesso na próxima requisição, não só no próximo login. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user instanceof User && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Seu acesso foi desativado. Fale com o responsável pelo estabelecimento.');
        }

        return $next($request);
    }
}
