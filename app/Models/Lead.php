<?php

namespace App\Models;

use App\Enums\BusinessType;
use App\Enums\LeadKind;
use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dúvida chegada pelo site. Grava-se localmente e fica no backoffice; a
 * equipa é avisada por email e pelo sino do painel.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property ?string $phone
 * @property ?string $message
 * @property ?int $property_id
 * @property ?BusinessType $business_type
 * @property LeadSource $source
 * @property ?array<string, mixed> $payload
 * @property bool $consent_contact
 * @property bool $consent_marketing
 * @property string $policy_version
 * @property ?string $ip_hash
 * @property ?string $user_agent
 */
class Lead extends Model
{
    /**
     * Campos do pedido de avaliação (payload), com os nomes que o site mostra.
     * Usado no email à equipa e no backoffice.
     */
    public const PAYLOAD_LABELS = [
        'address' => 'Morada',
        'city' => 'Concelho',
        'locality' => 'Freguesia',
        'property_type' => 'Tipo de imóvel',
        'bedrooms' => 'Tipologia',
        'area' => 'Área (m²)',
        'condition' => 'Estado de conservação',
        'estimate' => 'Estimativa mostrada no site',
    ];

    /**
     * Payload preenchido, pela ordem do site (os campos desconhecidos vão para o fim).
     *
     * @return array<string, string> rótulo → valor
     */
    public function payloadLabelled(): array
    {
        $payload = is_array($this->payload) ? array_filter($this->payload, fn ($v) => filled($v)) : [];
        $ordered = array_merge(array_intersect_key(self::PAYLOAD_LABELS, $payload), array_diff_key($payload, self::PAYLOAD_LABELS));
        $out = [];

        foreach (array_keys($ordered) as $key) {
            $out[self::PAYLOAD_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key))] = (string) $payload[$key];
        }

        return $out;
    }

    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Regista uma resposta enviada ao cliente. O envio do email é separado —
     * aqui só fica o registo, para a equipa saber o que já foi dito e por quem.
     */
    public function registarResposta(string $body, string $author): void
    {
        $this->replies = [...($this->replies ?? []), [
            'author' => $author,
            'body' => $body,
            'at' => now()->toIso8601String(),
        ]];
        $this->replied_at = now();
        $this->save();
    }

    /** Já foi respondida alguma vez? */
    public function foiRespondida(): bool
    {
        return $this->replied_at !== null;
    }

    protected function casts(): array
    {
        return [
            'business_type' => BusinessType::class,
            'source' => LeadSource::class,
            'kind' => LeadKind::class,
            'status' => LeadStage::class,
            'priority' => LeadPriority::class,
            'payload' => 'array',
            'replies' => 'array',
            'replied_at' => 'datetime',
            'consent_contact' => 'boolean',
            'consent_marketing' => 'boolean',
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Hash do IP com a APP_KEY como sal — permite rate limiting e auditoria sem guardar o IP. */
    public static function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
