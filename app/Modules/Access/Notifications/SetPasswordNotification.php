<?php

namespace App\Modules\Access\Notifications;

use App\Modules\Access\Models\User;
use App\Tenancy\TenantUrl;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Link para definir a senha: convite de usuário novo ou "esqueci minha senha".
 * Enviado de dentro do tenant; o link usa o domínio primário dele.
 */
class SetPasswordNotification extends Notification
{
    public function __construct(
        public readonly string $token,
        public readonly bool $invitation = false,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(User $user): string
    {
        return TenantUrl::to(tenant(), 'reset-password/'.$this->token.'?email='.urlencode($user->email));
    }

    public function toMail(User $notifiable): MailMessage
    {
        $establishment = tenant()->name;
        $minutes = config('auth.passwords.users.expire');

        $message = (new MailMessage)
            ->greeting("Olá, {$notifiable->name}!")
            ->salutation("Equipe {$establishment}");

        if ($this->invitation) {
            return $message
                ->subject("Seu acesso a {$establishment}")
                ->line("Você foi convidado para acessar o sistema de {$establishment}.")
                ->action('Definir minha senha', $this->url($notifiable))
                ->line("O link vale por {$minutes} minutos. Se expirar, use \"Esqueci minha senha\" na tela de login.");
        }

        return $message
            ->subject("Redefinição de senha · {$establishment}")
            ->line('Recebemos um pedido para redefinir a sua senha.')
            ->action('Redefinir senha', $this->url($notifiable))
            ->line("O link vale por {$minutes} minutos. Se você não pediu, ignore este e-mail.");
    }
}
