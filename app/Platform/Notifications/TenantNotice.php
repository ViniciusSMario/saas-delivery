<?php

namespace App\Platform\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail da plataforma para o dono de um estabelecimento (exclusão, cobrança, boas-vindas). */
class TenantNotice extends Notification
{
    /** @param  list<string>  $lines */
    public function __construct(
        public readonly string $subject,
        public readonly array $lines,
        public readonly ?string $actionText = null,
        public readonly ?string $actionUrl = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->subject)->greeting('Olá!');

        foreach ($this->lines as $line) {
            $message->line($line);
        }

        if ($this->actionText && $this->actionUrl) {
            $message->action($this->actionText, $this->actionUrl);
        }

        return $message->salutation('Equipe '.config('app.name'));
    }
}
