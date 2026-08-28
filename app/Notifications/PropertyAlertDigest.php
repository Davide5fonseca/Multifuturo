<?php

namespace App\Notifications;

use App\Models\Property;
use App\Models\PropertyAlert;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * Os imóveis novos que encaixam num alerta, num só email (alerts:send).
 * Leva sempre a ligação para deixar de receber.
 */
class PropertyAlertDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  Collection<int, Property>  $properties */
    public function __construct(
        private readonly PropertyAlert $alert,
        private readonly Collection $properties,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $alert = $this->alert;
        $agency = (string) config('agency.name');
        $count = $this->properties->count();
        $unsubscribeUrl = URL::signedRoute('alerts.unsubscribe', ['locale' => $alert->locale, 'token' => $alert->token]);

        $mail = (new MailMessage)
            ->subject("[{$agency}] ".trans_choice('ui.alerts.mail.digest_subject', $count, ['count' => $count]))
            ->greeting($alert->name ? __('ui.alerts.mail.hello_name', ['name' => $alert->name]) : __('ui.alerts.mail.hello'))
            ->line(trans_choice('ui.alerts.mail.digest_intro', $count, ['count' => $count, 'summary' => $alert->summary()]));

        foreach ($this->properties as $property) {
            $url = route('property.show', ['locale' => $alert->locale, 'property' => $property]);
            $price = Format::price($property->price, $property->currency, $property->business_type, $property->price_visible);
            $details = array_filter([
                Format::typology($property->bedrooms),
                Format::area($property->house_area ?: $property->gross_area),
                Format::location($property->locality, $property->city),
            ]);

            $mail->line("**[{$property->title}]({$url})**  \n{$price} · ".implode(' · ', $details));
        }

        return $mail
            ->action(__('ui.alerts.mail.digest_button'), $alert->listingUrl())
            ->line(__('ui.alerts.mail.unsubscribe', ['url' => $unsubscribeUrl]))
            ->salutation(__('ui.alerts.mail.salutation', ['agency' => $agency]));
    }
}
