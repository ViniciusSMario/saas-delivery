<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Models\User;
use App\Modules\Access\Notifications\SetPasswordNotification;
use Illuminate\Support\Facades\Password;

class SendSetPasswordLink
{
    /** Gera um token no password_reset_tokens do tenant e envia o link. */
    public function handle(User $user, bool $invitation = false): void
    {
        $token = Password::broker('users')->createToken($user);

        $user->notify(new SetPasswordNotification($token, $invitation));
    }
}
