<?php

namespace App\Notifications;

use App\Models\PropertyAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Double opt-in do alerta de imóveis: só depois de carregar aqui é que a
 * pessoa passa a receber. Vai no idioma em que pediu.
 */
class ConfirmPropertyAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly PropertyAlert $alert) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $alert = $this->alert;
        $agency = (string) config('agency.name');
        $confirmUrl = URL::signedRoute('alerts.confirm', ['locale' => $alert->locale, 'token' => $alert->token]);

        $mail = (new MailMessage)
            ->subject("[{$agency}] ".__('ui.alerts.mail.confirm_subject'))
            ->greeting($alert->name ? __('ui.alerts.mail.hello_name', ['name' => $alert->name]) : __('ui.alerts.mail.hello'))
            ->line(__('ui.alerts.mail.confirm_intro'))
            ->line('**'.$alert->summary().'**')
            ->action(__('ui.alerts.mail.confirm_button'), $confirmUrl)
            ->line(__('ui.alerts.mail.confirm_ignore'))
            ->salutation(__('ui.alerts.mail.salutation', ['agency' => $agency]));

        if ($email = config('agency.email')) {
            $mail->replyTo($email, $agency);
        }

        return $mail;
    }
}
