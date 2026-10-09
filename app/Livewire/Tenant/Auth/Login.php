<?php

namespace App\Livewire\Tenant\Auth;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Entrar')]
class Login extends Component
{
    public const MAX_ATTEMPTS = 5;

    #[Validate('required|email', as: 'e-mail')]
    public string $email = '';

    #[Validate('required|string', as: 'senha')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        $email = mb_strtolower(trim($this->email));
        // Rate limit por tenant + e-mail + IP.
        $key = 'login:'.tenant()->getKey().'|'.$email.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Muitas tentativas. Tente de novo em '.RateLimiter::availableIn($key).' segundos.',
            ]);
        }

        $credentials = ['email' => $email, 'password' => $this->password, 'is_active' => true];

        if (! Auth::guard('web')->attempt($credentials, $this->remember)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        /** @var User $user */
        $user = Auth::guard('web')->user();
        $user->forceFill(['last_login_at' => now()])->save();
        Audit::record('auth.login', $user, actor: $user);

        $this->redirectIntended(route('dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.tenant.auth.login');
    }
}
