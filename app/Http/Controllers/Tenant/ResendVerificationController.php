<?php

namespace App\Http\Controllers\Tenant;

use App\Platform\Actions\SelfSignup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/** Reenvia o e-mail de confirmação (aviso no painel do dono). */
class ResendVerificationController
{
    public function __invoke(): RedirectResponse
    {
        Gate::authorize('billing.manage');
        $tenant = tenant();

        if (! $tenant->email_verified_at && $tenant->billing_email) {
            SelfSignup::sendVerification($tenant);
        }

        return back()->with('status', 'Enviamos de novo o link de confirmação para '.$tenant->billing_email.'.');
    }
}
