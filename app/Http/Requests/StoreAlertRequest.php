<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\GuardsAgainstBots;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pedido de alerta de imóveis (formulário na listagem).
 *
 * Mesmo anti-spam das leads (honeypot + tempo mínimo). O consentimento é
 * obrigatório: sem ele não há alerta — a pessoa está a pedir emails.
 */
class StoreAlertRequest extends FormRequest
{
    use GuardsAgainstBots;

    /** Erros num saco próprio: a listagem tem outros formulários. */
    protected $errorBag = 'alerts';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'listing' => ['required', 'in:buy,rent'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'name' => ['nullable', 'string', 'max:120'],
            'consent' => ['accepted'],
            'criteria' => ['nullable', 'array:type,bedrooms,city,locality,price_min,price_max,area_min,features'],
            'criteria.type' => ['nullable', 'string', 'max:64'],
            'criteria.bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'criteria.city' => ['nullable', 'string', 'max:96'],
            'criteria.locality' => ['nullable', 'string', 'max:96'],
            'criteria.price_min' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'criteria.price_max' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'criteria.area_min' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'criteria.features' => ['nullable', 'array', 'max:12'],
            'criteria.features.*' => ['string', 'max:96'],
            'form_ts' => ['nullable', 'string', 'max:96'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'email' => __('validation.attributes.email'),
            'consent' => __('ui.alerts.consent_attribute'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $criteria = $this->input('criteria');

        // Campos vazios do formulário chegam como '' — para as regras de inteiro, são nulos.
        if (is_array($criteria)) {
            $this->merge(['criteria' => array_map(fn ($v) => $v === '' ? null : $v, $criteria)]);
        }
    }
}
