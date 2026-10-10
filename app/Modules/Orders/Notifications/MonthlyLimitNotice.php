<?php

namespace App\Modules\Orders\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso ao dono: o estabelecimento chegou a 80% (ou passou) dos pedidos do mês do plano. */
class MonthlyLimitNotice extends Notification
{
    public function __construct(
        public readonly int $percent,
        public readonly int $count,
        public readonly int $limit,
        public readonly string $establishment,
        public readonly string $subscriptionUrl,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $passed = $this->percent >= 100;

        return (new MailMessage)
            ->subject($passed ? "Você passou do limite de pedidos do mês ({$this->establishment})" : "Você já usou {$this->percent}% dos pedidos do mês ({$this->establishment})")
            ->greeting('Olá!')
            ->line("{$this->establishment} recebeu {$this->count} pedidos este mês. O seu plano inclui {$this->limit}.")
            ->line($passed
                ? 'Os pedidos continuam entrando normalmente. Para não ficar acima do plano, considere trocar para um plano maior.'
                : 'Os pedidos continuam entrando normalmente. Se o movimento seguir assim, vale olhar os planos.')
            ->action('Ver planos', $this->subscriptionUrl)
            ->salutation('Equipe '.config('app.name'));
    }
}
