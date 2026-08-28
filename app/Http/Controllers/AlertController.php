<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAlertRequest;
use App\Models\Lead;
use App\Models\PropertyAlert;
use App\Notifications\ConfirmPropertyAlert;
use App\Support\PropertyFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;

/**
 * Alertas de imóveis: pedido (formulário na listagem), confirmação e
 * cancelamento (ligações assinadas enviadas por email).
 */
class AlertController extends Controller
{
    public function store(StoreAlertRequest $request): RedirectResponse
    {
        // Spam: aceitar em silêncio, sem gravar. O bot vê o mesmo que um humano.
        if ($request->looksLikeSpam()) {
            return back()->with('alert_sent', true);
        }

        $data = $request->validated();
        $email = mb_strtolower(trim($data['email']));
        $criteria = PropertyFilters::sanitize($data['criteria'] ?? []);

        // O mesmo email com os mesmos critérios não cria um segundo alerta.
        $alert = PropertyAlert::query()
            ->where('email', $email)
            ->where('listing', $data['listing'])
            ->whereRaw('criteria = ?::jsonb', [json_encode($criteria)])
            ->whereNull('unsubscribed_at')
            ->first();

        if ($alert?->confirmed_at) {
            return back()->with('alert_exists', true);
        }

        $alert ??= PropertyAlert::query()->create([
            'email' => $email,
            'name' => isset($data['name']) ? trim($data['name']) : null,
            'locale' => app()->getLocale(),
            'listing' => $data['listing'],
            'criteria' => $criteria,
            'token' => PropertyAlert::newToken(),
            'policy_version' => (string) config('agency.privacy_policy_version'),
            'ip_hash' => Lead::hashIp($request->ip()),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        // Double opt-in: nada é enviado antes de a pessoa confirmar o email.
        Notification::route('mail', $email)->notify((new ConfirmPropertyAlert($alert))->locale($alert->locale));

        return back()->with('alert_sent', true);
    }

    /** Ligação assinada do email de confirmação. Reconfirmar um alerta cancelado volta a ativá-lo. */
    public function confirm(string $token): View
    {
        $alert = PropertyAlert::query()->where('token', $token)->first();

        if (! $alert) {
            return view('pages.alert-status', ['state' => 'invalid', 'alert' => null]);
        }

        $alert->forceFill(['confirmed_at' => $alert->confirmed_at ?? now(), 'unsubscribed_at' => null])->save();

        return view('pages.alert-status', ['state' => 'confirmed', 'alert' => $alert]);
    }

    /** Ligação assinada presente em todos os emails de alerta. */
    public function unsubscribe(string $token): View
    {
        $alert = PropertyAlert::query()->where('token', $token)->first();

        if (! $alert) {
            return view('pages.alert-status', ['state' => 'invalid', 'alert' => null]);
        }

        if ($alert->unsubscribed_at === null) {
            $alert->forceFill(['unsubscribed_at' => now()])->save();
        }

        return view('pages.alert-status', ['state' => 'unsubscribed', 'alert' => $alert]);
    }
}
