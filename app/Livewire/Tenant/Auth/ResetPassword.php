<?php

namespace App\Livewire\Tenant\Auth;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Definir senha')]
class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    protected function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['email' => 'e-mail', 'password' => 'senha'];
    }

    public function save(): void
    {
        $this->validate();

        $status = Password::broker('users')->reset(
            [
                'email' => mb_strtolower(trim($this->email)),
                'token' => $this->token,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                Audit::record('auth.password_reset', $user, actor: $user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'Este link é inválido ou expirou. Peça um novo em "Esqueci minha senha".',
            ]);
        }

        session()->flash('status', 'Senha definida. Entre com seu e-mail e a nova senha.');
        $this->redirectRoute('login');
    }

    public function render()
    {
        return view('livewire.tenant.auth.reset-password');
    }
}
