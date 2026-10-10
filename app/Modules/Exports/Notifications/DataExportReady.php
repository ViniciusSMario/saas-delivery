<?php

namespace App\Modules\Exports\Notifications;

use App\Modules\Exports\Models\DataExport;
use App\Tenancy\TenantUrl;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DataExportReady extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sua exportação de dados está pronta')
            ->greeting('Olá!')
            ->line('A exportação dos dados de '.tenant()->name.' terminou.')
            ->line('Entre no sistema para baixar o arquivo. Ele fica disponível por '.DataExport::KEEP_DAYS.' dias.')
            ->action('Baixar exportação', TenantUrl::to(tenant(), 'exportar-dados'))
            ->salutation('Equipe '.config('app.name'));
    }
}
