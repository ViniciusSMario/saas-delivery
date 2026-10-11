<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Access\Audit;
use App\Modules\Push\Models\PushSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($user = Auth::guard('web')->user()) {
            Audit::record('auth.logout', $user, actor: $user);

            // Celular compartilhado: quem sai deixa de receber os avisos neste aparelho.
            if ($endpoint = $request->session()->get('push_endpoint')) {
                PushSubscription::where('endpoint', $endpoint)->where('user_id', $user->id)->delete();
            }
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
