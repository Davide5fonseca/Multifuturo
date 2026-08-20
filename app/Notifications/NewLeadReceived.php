<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso à agência: chegou um novo pedido pelo site (substitui o envio ao CRM).
 * Vai para agency.email por cada lead criada; a lead fica sempre guardada na
 * base de dados e visível no backoffice.
 */
class NewLeadReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Lead $lead) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->lead;

        $subject = match ($lead->source->value) {
            'property' => 'Pedido de informação'.($lead->property?->reference ? " — {$lead->property->reference}" : ''),
            'valuation' => 'Pedido de avaliação de imóvel',
            default => 'Novo contacto pelo website',
        };

        $mail = (new MailMessage)
            ->subject("[Multifuturo] {$subject}")
            ->greeting('Novo pedido pelo website')
            ->line("**Nome:** {$lead->name}")
            ->line("**Email:** {$lead->email}")
            ->line('**Telefone:** '.($lead->phone ?: '—'));

        if ($lead->property) {
            $mail->line("**Imóvel:** {$lead->property->reference} — ".($lead->property->title ?: $lead->property->slug));
        }

        if (filled($lead->message)) {
            $mail->line('**Mensagem:**')->line($lead->message);
        }

        if (is_array($lead->payload) && $lead->payload !== []) {
            $mail->line('**Dados do imóvel a avaliar:**');
            foreach ($lead->payload as $key => $value) {
                if (filled($value)) {
                    $mail->line('· '.ucfirst(str_replace('_', ' ', $key)).": {$value}");
                }
            }
        }

        return $mail
            ->line('Consentimentos: contacto '.($lead->consent_contact ? 'sim' : 'não').' · comunicações '.($lead->consent_marketing ? 'sim' : 'não'))
            ->line('O pedido está guardado no backoffice.');
    }
}
