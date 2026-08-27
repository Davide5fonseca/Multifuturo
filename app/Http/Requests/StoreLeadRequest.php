<?php

namespace App\Http\Requests;

use App\Enums\LeadSource;
use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Validação dos formulários de lead (imóvel, contacto geral, avaliação).
 *
 * Anti-spam sem CAPTCHA de terceiros:
 *  - honeypot: o campo "website" está escondido por CSS; humanos deixam-no vazio,
 *    bots preenchem-no. Se vier preenchido, o pedido é aceite em silêncio
 *    (200/redirect) mas NÃO cria lead — não vale a pena ensinar o bot;
 *  - tempo mínimo: o formulário traz a hora em que foi renderizado (assinada);
 *    submissões em menos de N segundos são tratadas como spam;
 *  - rate limiting por IP no route (ver routes/web.php).
 *
 * RGPD: consent_contact e consent_marketing são DOIS booleanos separados,
 * ambos falsos por defeito. Nunca são forçados a true.
 */
class StoreLeadRequest extends FormRequest
{
    /** Segundos mínimos entre renderizar o formulário e submeter. */
    public const MIN_SECONDS = 3;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source' => ['required', new Enum(LeadSource::class)],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[+0-9()\s.-]{6,32}$/'],
            'message' => ['nullable', 'string', 'max:3000'],
            'property_slug' => ['nullable', 'string', 'max:191', Rule::exists(Property::class, 'slug')],
            'consent_contact' => ['nullable', 'boolean'],
            'consent_marketing' => ['nullable', 'boolean'],

            // Campos extra (avaliação). Só chaves conhecidas; valores curtos.
            'payload' => ['nullable', 'array:address,city,locality,property_type,bedrooms,area,condition,estimate'],
            'payload.address' => ['nullable', 'string', 'max:255'],
            'payload.city' => ['nullable', 'string', 'max:96'],
            'payload.locality' => ['nullable', 'string', 'max:191'],
            'payload.property_type' => ['nullable', 'string', 'max:64'],
            'payload.bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'payload.area' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'payload.condition' => ['nullable', 'string', 'max:64'],
            'payload.estimate' => ['nullable', 'string', 'max:64'],   // intervalo mostrado pelo simulador

            // Anti-spam. O honeypot ("website") não tem regra: é lido em looksLikeSpam().
            'form_ts' => ['nullable', 'string', 'max:96'],       // timestamp assinado, opcional
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => __('validation.attributes.name'),
            'email' => __('validation.attributes.email'),
            'phone' => __('validation.attributes.phone'),
            'message' => __('validation.attributes.message'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'consent_contact' => $this->boolean('consent_contact'),
            'consent_marketing' => $this->boolean('consent_marketing'),
        ]);
    }

    /**
     * True quando o pedido tem cheiro de bot: honeypot preenchido ou submissão
     * demasiado rápida. Avaliado DEPOIS da validação (o "website" com max:0
     * falharia a validação com mensagem — preferimos aceitar em silêncio).
     */
    public function looksLikeSpam(): bool
    {
        if (filled($this->input('website'))) {
            return true;
        }

        $ts = $this->input('form_ts');
        if (is_string($ts) && $ts !== '') {
            $renderedAt = self::verifyTimestamp($ts);
            if ($renderedAt === null || (time() - $renderedAt) < self::MIN_SECONDS) {
                return true;
            }
        }

        return false;
    }

    /** Timestamp assinado com a APP_KEY, para o formulário não poder forjar a hora. */
    public static function signedTimestamp(?int $time = null): string
    {
        $time ??= time();

        return $time.'.'.hash_hmac('sha256', (string) $time, (string) config('app.key'));
    }

    private static function verifyTimestamp(string $value): ?int
    {
        [$time, $sig] = array_pad(explode('.', $value, 2), 2, '');

        if (! ctype_digit($time) || ! hash_equals(hash_hmac('sha256', $time, (string) config('app.key')), $sig)) {
            return null;
        }

        return (int) $time;
    }
}
