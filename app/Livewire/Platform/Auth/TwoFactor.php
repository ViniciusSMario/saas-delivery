<?php

namespace App\Livewire\Platform\Auth;

use App\Platform\Models\PlatformUser;
use App\Platform\TwoFactor as TwoFactorEngine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Segunda etapa do login do admin. Sem 2FA configurado, mostra o QR code e
 * só aceita o login depois que o primeiro código for confirmado.
 */
#[Layout('components.layouts.guest', ['brand' => 'Admin da plataforma', 'eyebrow' => 'Equipe do SaaS'])]
#[Title('Verificação em duas etapas')]
class TwoFactor extends Component
{
    public const SETUP_SECRET_KEY = 'platform.2fa.setup_secret';

    public string $code = '';

    public function mount(): void
    {
        if (! $this->pendingUser()) {
            $this->redirectRoute('platform.login');
        }
    }

    public function verify(TwoFactorEngine $engine): void
    {
        $this->resetErrorBag();
        $user = $this->pendingUser() ?? abort(419);
        $key = 'platform-2fa:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Muitas tentativas. Tente de novo em '.RateLimiter::availableIn($key).' segundos.',
            ]);
        }

        $settingUp = ! $user->hasTwoFactor();
        $secret = $settingUp ? session(self::SETUP_SECRET_KEY) : $user->two_factor_secret;

        if (! $secret || ! $engine->verify($secret, $this->code)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['code' => 'Código inválido. Confira o horário do celular e tente de novo.']);
        }

        RateLimiter::clear($key);

        if ($settingUp) {
            $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        session()->forget([Login::PENDING_KEY, self::SETUP_SECRET_KEY]);
        Auth::guard('platform')->login($user);
        session()->regenerate();

        $this->redirectRoute('platform.tenants.index');
    }

    private function pendingUser(): ?PlatformUser
    {
        $id = session(Login::PENDING_KEY);

        return $id ? PlatformUser::where('is_active', true)->find($id) : null;
    }

    public function render()
    {
        $engine = app(TwoFactorEngine::class);
        $user = $this->pendingUser();
        $qr = null;
        $secret = null;

        if ($user && ! $user->hasTwoFactor()) {
            $secret = session(self::SETUP_SECRET_KEY) ?? tap($engine->generateSecret(), fn ($s) => session([self::SETUP_SECRET_KEY => $s]));
            $qr = $engine->qrCodeSvg($user->email, $secret);
        }

        return view('livewire.platform.auth.two-factor', ['setup' => $qr !== null, 'qr' => $qr, 'secret' => $secret]);
    }
}
