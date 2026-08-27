<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso à equipa: chegou um novo pedido pelo site (informação sobre um
 * imóvel, avaliação ou contacto geral). Vai para cada administrador do
 * backoffice e para agency.email, por cada lead criada; a lead fica sempre
 * guardada na base de dados e visível no backoffice.
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
            'valuation' => 'Pedido de avaliação'.(filled($lead->payload['city'] ?? null) ? " — {$lead->payload['city']}" : ''),
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

        if ($lead->payloadLabelled() !== []) {
            $mail->line('**Dados do imóvel a avaliar:**');
            foreach ($lead->payloadLabelled() as $label => $value) {
                $mail->line("· {$label}: {$value}");
            }
        }

        return $mail
            ->line('Consentimentos: contacto '.($lead->consent_contact ? 'sim' : 'não').' · comunicações '.($lead->consent_marketing ? 'sim' : 'não'))
            ->action('Abrir o pedido no backoffice', route('filament.admin.resources.leads.edit', $lead))
            ->line('Pode responder ao cliente a partir daí.');
    }
}
