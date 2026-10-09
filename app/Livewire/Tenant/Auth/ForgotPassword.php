<?php

namespace App\Livewire\Tenant\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Esqueci minha senha')]
class ForgotPassword extends Component
{
    #[Validate('required|email', as: 'e-mail')]
    public string $email = '';

    public bool $sent = false;

    public function send(): void
    {
        $this->validate();

        // Token no password_reset_tokens do tenant; o broker limita um pedido por minuto.
        Password::broker('users')->sendResetLink(['email' => mb_strtolower(trim($this->email)), 'is_active' => true]);

        // Mesma resposta exista ou não o e-mail: não revela quem tem conta.
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.tenant.auth.forgot-password');
    }
}
