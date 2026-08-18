<?php

namespace App\Jobs;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Notifications\LeadDeliveryFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

/**
 * Envia uma lead ao CASAFARI CRM (API de leads). Único ponto de escrita no CRM.
 *
 * ARMADILHA: a API devolve HTTP 200 mesmo quando falha. Por isso não basta
 * ->throw(); valida-se `status === true` no JSON e lança-se exceção caso
 * contrário — só assim o job entra em retry e, no fim, em failed().
 *
 * tries=5 com backoff crescente (1 min, 5 min, 15 min, 1 h): um CRM em baixo
 * durante a noite não perde a lead — ela já está gravada localmente.
 */
class SendLeadToCasafari implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600];

    public int $timeout = 60;

    public function __construct(public readonly int $leadId) {}

    public function handle(): void
    {
        $lead = Lead::query()->with('property')->find($this->leadId);

        if ($lead === null) {
            return; // apagada entretanto — nada a fazer
        }

        if ($lead->crm_status === LeadStatus::Sent) {
            return; // idempotente: já foi
        }

        $token = (string) config('casafari.token');
        if ($token === '') {
            throw new RuntimeException('CASAFARI_TOKEN não está definido; a lead fica pending.');
        }

        $lead->increment('attempts');

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->asForm()
                ->post((string) config('casafari.lead_url'), $this->payload($lead, $token));
        } catch (Throwable $e) {
            $this->recordAttempt($lead, null, $e->getMessage());
            throw $e;
        }

        // Guardamos SEMPRE a resposta — é o que permite perceber o que o CRM disse.
        $json = $response->json();
        $ok = $response->successful() && is_array($json) && ($json['status'] ?? null) === true;

        if (! $ok) {
            $error = sprintf(
                'CRM respondeu HTTP %d com status=%s: %s',
                $response->status(),
                var_export($json['status'] ?? null, true),
                mb_substr($this->messageFrom($response), 0, 500)
            );
            $this->recordAttempt($lead, $json ?? ['raw' => mb_substr($response->body(), 0, 2000)], $error);

            throw new RuntimeException($error);
        }

        $lead->forceFill([
            'crm_status' => LeadStatus::Sent,
            'crm_response' => $json,
            'sent_at' => now(),
            'last_error' => null,
        ])->save();
    }

    /**
     * Última tentativa esgotada: marca failed, regista e notifica. A lead continua
     * na base de dados para envio manual.
     */
    public function failed(?Throwable $exception): void
    {
        $lead = Lead::query()->find($this->leadId);
        if ($lead === null) {
            return;
        }

        $lead->forceFill([
            'crm_status' => LeadStatus::Failed,
            'last_error' => mb_substr((string) $exception?->getMessage(), 0, 2000),
        ])->save();

        Log::error('Lead não entregue ao CASAFARI após todas as tentativas', [
            'lead_id' => $lead->id,
            'attempts' => $lead->attempts,
            'error' => $exception?->getMessage(),
        ]);

        if ($email = config('casafari.alert_email')) {
            Notification::route('mail', $email)->notify(new LeadDeliveryFailed($lead));
        }
    }

    /**
     * Corpo do POST (form-urlencoded), com os nomes de campo da API de leads.
     *
     * @return array<string, string|int|bool>
     */
    private function payload(Lead $lead, string $token): array
    {
        $data = [
            'Token' => $token,
            'CustomerOriginID' => (string) config('casafari.customer_origin_id'),
            'EntityName' => $lead->name,
            'EntityEmail' => $lead->email,
            'EntityPhone' => (string) $lead->phone,
            'Message' => $this->messageFor($lead),
            'CreateProfile' => 'true',
            'EntityCulture' => 'pt',
            'EntityType' => (string) config('casafari.lead_entity_type'),
            'AssignBrokerIDFromProperty' => 'true',
            // RGPD: dois consentimentos distintos, tal como o utilizador os deu. Nunca forçados.
            'IncludeOptIn' => $lead->consent_contact ? 'true' : 'false',
            'IncludeMailing' => $lead->consent_marketing ? 'true' : 'false',
        ];

        // PropertyID = internal_id do CRM (não o nosso id). Só quando há imóvel.
        if ($lead->property?->internal_id) {
            $data['PropertyID'] = $lead->property->internal_id;
        }

        return $data;
    }

    /** Mensagem enviada ao CRM: texto do utilizador + contexto útil (origem, referência, dados de avaliação). */
    private function messageFor(Lead $lead): string
    {
        $lines = [];

        $lines[] = match ($lead->source->value) {
            'property' => 'Pedido de informação sobre imóvel'.($lead->property?->reference ? " (ref. {$lead->property->reference})" : ''),
            'valuation' => 'Pedido de avaliação — "Quanto vale a minha casa?"',
            default => 'Contacto geral pelo website',
        };

        if (filled($lead->message)) {
            $lines[] = '';
            $lines[] = trim((string) $lead->message);
        }

        if (is_array($lead->payload) && $lead->payload !== []) {
            $lines[] = '';
            foreach ($lead->payload as $key => $value) {
                if (filled($value)) {
                    $lines[] = ucfirst(str_replace('_', ' ', $key)).': '.$value;
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>|null  $response
     */
    private function recordAttempt(Lead $lead, ?array $response, string $error): void
    {
        $lead->forceFill([
            'crm_response' => $response,
            'last_error' => mb_substr($error, 0, 2000),
        ])->save();
    }

    private function messageFrom(Response $response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            foreach (['message', 'error', 'Message', 'errors'] as $key) {
                if (isset($json[$key])) {
                    return is_string($json[$key]) ? $json[$key] : json_encode($json[$key]);
                }
            }
        }

        return (string) $response->body();
    }
}
