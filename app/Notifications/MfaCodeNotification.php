<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * O código de seis algarismos da segunda etapa do login. Enviado na hora
 * (notifyNow), nunca em fila: a pessoa está à espera dele.
 */
class MfaCodeNotification extends Notification
{
    public function __construct(
        private readonly string $code,
        private readonly int $minutes,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // O email vem do portal (a ponte), não da agência.
        $portal = (string) config('portal.name');

        return (new MailMessage)
            ->subject("[{$portal}] Código de verificação: {$this->code}")
            ->greeting('Olá,')
            ->line('O seu código para entrar no portal é:')
            ->line("# {$this->code}")
            ->line("Vale {$this->minutes} minutos e só serve uma vez.")
            ->line('Se não foi você a tentar entrar, ignore este email — sem o código ninguém entra. Se acontecer outra vez, mude a sua palavra-passe.')
            ->salutation("Com os melhores cumprimentos,\n{$portal}");
    }
}
