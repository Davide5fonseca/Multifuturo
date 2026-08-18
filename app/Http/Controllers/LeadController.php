<?php

namespace App\Http\Controllers;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Http\Requests\StoreLeadRequest;
use App\Jobs\SendLeadToCasafari;
use App\Models\Lead;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

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

        // Só depois de gravada: se a queue/CRM falharem, o contacto já existe.
        SendLeadToCasafari::dispatch($lead->id)->afterCommit();

        return $this->accepted($request);
    }

    private function accepted(StoreLeadRequest $request): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => __('ui.lead.success')], 201);
        }

        return back()->with('lead_sent', true);
    }
}
