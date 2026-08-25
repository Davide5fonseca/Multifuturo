<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Resposta da agência a uma dúvida chegada pelo site.
 *
 * Vai em fila, como o aviso de entrada: se o servidor de email estiver lento
 * ou em baixo, quem está no backoffice não fica à espera — e a resposta fica
 * registada na dúvida de qualquer forma.
 */
class LeadReply extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Lead $lead,
        private readonly string $body,
        private readonly string $author,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->lead;
        $agency = (string) config('agency.name');

        $subject = $lead->property?->reference
            ? "Resposta ao seu pedido — {$lead->property->reference}"
            : 'Resposta ao seu pedido';

        $mail = (new MailMessage)
            ->subject("[{$agency}] {$subject}")
            ->greeting($lead->name ? "Olá {$lead->name}," : 'Olá,');

        // A mensagem da equipa, parágrafo a parágrafo.
        foreach (preg_split('/\R{2,}/', trim($this->body)) ?: [] as $paragrafo) {
            $mail->line(trim($paragrafo));
        }

        if ($lead->property && $lead->property->isPublishable()) {
            $mail->action('Ver o imóvel', route('property.show', $lead->property));
        }

        $mail->salutation("Com os melhores cumprimentos,\n{$this->author}\n{$agency}");

        // Quem responder ao email vai dar à caixa da agência, não ao servidor.
        if ($email = config('agency.email')) {
            $mail->replyTo($email, $agency);
        }

        return $mail;
    }
}
