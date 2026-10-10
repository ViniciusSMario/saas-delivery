<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use App\Platform\Actions\SupportImpersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Entrada e saída do modo suporte, no endereço do estabelecimento. */
class SupportAccessController
{
    public function enter(Request $request, string $token, SupportImpersonation $support): RedirectResponse
    {
        $access = $support->redeem(tenant(), $token);
        abort_unless($access, 403, 'Link de suporte inválido, expirado ou já usado.');

        $user = User::where('is_active', true)->findOrFail($access->tenant_user_id);

        // Sessão nova: nada da sessão anterior (de outra pessoa) sobra.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put(SupportImpersonation::SESSION_KEY, [
            'id' => $access->id,
            'email' => $access->platform_email,
            'user' => $user->name,
        ]);

        $support->audit($access, $user);

        return redirect()->route('dashboard');
    }

    public function leave(Request $request, SupportImpersonation $support): RedirectResponse
    {
        $access = $request->session()->get(SupportImpersonation::SESSION_KEY);

        if ($access) {
            $support->end((int) $access['id']);

            if ($user = Auth::guard('web')->user()) {
                Audit::record('support.logout', $user, ['equipe' => $access['email']], $user);
            }
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away('http'.(request()->isSecure() ? 's' : '').'://'.config('tenancy.platform_domain').(parse_url(config('app.url'), PHP_URL_PORT) ? ':'.parse_url(config('app.url'), PHP_URL_PORT) : '').'/tenants/'.tenant()->id);
    }
}
