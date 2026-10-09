<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Access\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($user = Auth::guard('web')->user()) {
            Audit::record('auth.logout', $user, actor: $user);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
