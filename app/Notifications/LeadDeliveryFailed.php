<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso interno: uma lead não chegou ao CRM depois de todas as tentativas.
 * Vai para casafari.alert_email; contém o suficiente para o envio manual.
 */
class LeadDeliveryFailed extends Notification
{
    public function __construct(private readonly Lead $lead) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->lead;

        return (new MailMessage)
            ->subject("[Multifuturo] Lead #{$lead->id} não entregue ao CASAFARI")
            ->greeting('Lead por entregar')
            ->line("A lead #{$lead->id} ({$lead->source->value}) falhou após {$lead->attempts} tentativas e ficou marcada como \"failed\".")
            ->line("Nome: {$lead->name}")
            ->line("Email: {$lead->email}")
            ->line('Telefone: '.($lead->phone ?: '—'))
            ->line('Imóvel: '.($lead->property?->reference ?: '—'))
            ->line('Último erro: '.($lead->last_error ?: '—'))
            ->line('A lead continua guardada na base de dados; introduza-a manualmente no CRM ou reenvie quando o serviço estiver reposto.');
    }
}
