<?php

namespace App\Http\Controllers;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use App\Notifications\NewLeadReceived;
use Filament\Actions\Action;
use Filament\Notifications\Notification as BackofficeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;

/**
 * Receção de leads. Fluxo: FormRequest valida → grava local PRIMEIRO →
 * dispatch do job para a queue. O CRM nunca é chamado no pedido HTTP.
 */
class LeadController extends Controller
{
    public function store(StoreLeadRequest $request): RedirectResponse|JsonResponse
    {
        // Spam: aceitar em silêncio, sem gravar. O bot vê o mesmo que um humano.
        if ($request->looksLikeSpam()) {
            return $this->accepted($request);
        }

        $data = $request->validated();

        $property = isset($data['property_slug'])
            ? Property::query()->where('slug', $data['property_slug'])->first()
            : null;

        $lead = Lead::query()->create([
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'phone' => isset($data['phone']) ? trim($data['phone']) : null,
            'message' => isset($data['message']) ? trim($data['message']) : null,
            'property_id' => $property?->id,
            'business_type' => $property?->business_type,
            'source' => LeadSource::from($data['source']),
            'payload' => $data['payload'] ?? null,
            'consent_contact' => (bool) ($data['consent_contact'] ?? false),
            'consent_marketing' => (bool) ($data['consent_marketing'] ?? false),
            'policy_version' => (string) config('agency.privacy_policy_version'),
            'ip_hash' => Lead::hashIp($request->ip()),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'crm_status' => LeadStatus::Pending,
        ]);

        // Aviso à agência (sem CRM: a lead vive na nossa BD e no backoffice).
        // Só depois de gravada: se a queue/email falharem, o contacto já existe.
        if ($email = config('agency.email')) {
            Notification::route('mail', $email)->notify(new NewLeadReceived($lead));
        }

        // E no sino do backoffice, para toda a equipa.
        $this->notifyBackoffice($lead);

        return $this->accepted($request);
    }

    /** Notificação no sino do painel, com ligação directa ao pedido. */
    private function notifyBackoffice(Lead $lead): void
    {
        $title = match ($lead->source->value) {
            'property' => 'Novo pedido de informação'.($lead->property?->reference ? " — {$lead->property->reference}" : ''),
            'valuation' => 'Novo pedido de avaliação',
            default => 'Novo contacto pelo website',
        };

        BackofficeNotification::make()
            ->title($title)
            ->body($lead->name.($lead->phone ? ' · '.$lead->phone : ''))
            ->icon('heroicon-o-envelope')
            ->iconColor('primary')
            ->actions([
                Action::make('abrir')
                    ->label('Abrir pedido')
                    ->url(route('filament.admin.resources.leads.edit', $lead)),
            ])
            ->sendToDatabase(User::all());
    }

    private function accepted(StoreLeadRequest $request): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => __('ui.lead.success')], 201);
        }

        return back()->with('lead_sent', true);
    }
}
