<?php

namespace App\Livewire\Platform\Auth;

use App\Platform\Models\PlatformUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Primeira etapa: e-mail e senha. O login só se completa depois do código 2FA
 * (TwoFactor); até lá o usuário fica apenas "pendente" na sessão.
 */
#[Layout('components.layouts.guest', ['brand' => 'Admin da plataforma', 'eyebrow' => 'Equipe do SaaS'])]
#[Title('Entrar')]
class Login extends Component
{
    public const PENDING_KEY = 'platform.2fa.pending';

    #[Validate('required|email', as: 'e-mail')]
    public string $email = '';

    #[Validate('required|string', as: 'senha')]
    public string $password = '';

    public function login(): void
    {
        $this->validate();

        $email = mb_strtolower(trim($this->email));
        $key = 'platform-login:'.$email.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Muitas tentativas. Tente de novo em '.RateLimiter::availableIn($key).' segundos.',
            ]);
        }

        $credentials = ['email' => $email, 'password' => $this->password, 'is_active' => true];
        $provider = Auth::guard('platform')->getProvider();
        $user = $provider->retrieveByCredentials($credentials);

        if (! $user instanceof PlatformUser || ! $provider->validateCredentials($user, $credentials)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }

        RateLimiter::clear($key);
        session()->regenerate();
        session()->put(self::PENDING_KEY, $user->getKey());

        $this->redirectRoute('platform.two-factor');
    }

    public function render()
    {
        return view('livewire.platform.auth.login');
    }
}
