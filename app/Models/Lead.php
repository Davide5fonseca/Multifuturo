<?php

namespace App\Models;

use App\Enums\BusinessType;
use App\Enums\LeadKind;
use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lead captada no site. Grava-se localmente primeiro; o envio ao CASAFARI é
 * feito pelo job SendLeadToCasafari, que atualiza crm_status/crm_response.
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
 * @property LeadStatus $crm_status
 * @property ?array<string, mixed> $crm_response
 * @property ?Carbon $sent_at
 * @property int $attempts
 * @property ?string $last_error
 */
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'business_type' => BusinessType::class,
            'source' => LeadSource::class,
            'kind' => LeadKind::class,
            'status' => LeadStage::class,
            'priority' => LeadPriority::class,
            'crm_status' => LeadStatus::class,
            'payload' => 'array',
            'crm_response' => 'array',
            'consent_contact' => 'boolean',
            'consent_marketing' => 'boolean',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
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
