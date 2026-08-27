{{--
    Formulário de lead — um componente para as três origens:
      source="property"   ficha de imóvel (passar :property)
      source="contact"    contacto geral
      source="valuation"  "Quanto vale a minha casa?" (campos extra em payload[...])

    Server-rendered, funciona sem JavaScript. Anti-spam: honeypot "website"
    (escondido por CSS) + timestamp assinado "form_ts". RGPD: duas checkboxes
    separadas, ambas desmarcadas por defeito, e aviso com link para a política.

    Visual mínimo com os tokens da marca — a re-skinnar na Fase 4 com o layout final.
--}}
@props([
    'source' => 'contact',
    'property' => null,
])

@php
    $isValuation = $source === 'valuation';
    $isProperty = $source === 'property' && $property;
    $defaultMessage = $isProperty ? __('ui.lead.message_property', ['reference' => $property->reference ?? $property->internal_id]) : '';
    $formId = 'lead-'.$source.($property?->id ? '-'.$property->id : '');
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl bg-sand-100 border border-sand-200 p-6 sm:p-8']) }} id="{{ $formId }}"
    @if ($isValuation)
        {{-- Recebe os dados do simulador de estimativa (mesma página) e preenche os campos. --}}
        x-data="{ fill(d) {
            const set = (n, v) => { const el = document.getElementById('{{ $formId }}-' + n); if (el) el.value = v ?? ''; };
            set('city', d.city); set('ptype', d.type); set('area', d.area); set('condition', d.condition); set('estimate', d.estimate);
            const a = document.getElementById('{{ $formId }}-address'); if (a && d.locality && ! a.value.trim()) a.value = d.locality;
            const m = document.getElementById('{{ $formId }}-message'); if (m && ! m.value.trim()) m.value = d.message;
        } }"
        x-on:valuation-estimate.window="fill($event.detail)"
    @endif
>
    <h2 class="text-2xl">{{ __('ui.lead.title_'.$source) }}</h2>
    <p class="mt-2 text-sm text-ink-muted">{{ __('ui.lead.lead_'.$source) }}</p>

    @if (session('lead_sent'))
        <p class="mt-6 border-l-2 border-olive-600 bg-sand-50 px-4 py-3 text-sm text-ink" role="status">{{ __('ui.lead.success') }}</p>
    @endif

    @if ($errors->any())
        <p class="mt-6 border-l-2 border-error bg-sand-50 px-4 py-3 text-sm text-error" role="alert">{{ __('ui.lead.error') }}</p>
    @endif

    <form method="post" action="{{ route('leads.store') }}" class="mt-6 grid gap-5" novalidate>
        @csrf
        <input type="hidden" name="source" value="{{ $source }}">
        <input type="hidden" name="form_ts" value="{{ \App\Http\Requests\StoreLeadRequest::signedTimestamp() }}">
        @if ($isProperty)
            <input type="hidden" name="property_slug" value="{{ $property->slug }}">
        @endif

        {{-- Honeypot: invisível para humanos; bots preenchem. Fora do fluxo de tab. --}}
        <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
            <label for="{{ $formId }}-website">Website</label>
            <input type="text" id="{{ $formId }}-website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="{{ $formId }}-name" class="label">{{ __('ui.lead.name') }}</label>
                <input id="{{ $formId }}-name" name="name" type="text" required autocomplete="name" value="{{ old('name') }}" class="field mt-2 @error('name') border-error @enderror">
                @error('name')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="{{ $formId }}-email" class="label">{{ __('ui.lead.email') }}</label>
                <input id="{{ $formId }}-email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}" class="field mt-2 @error('email') border-error @enderror">
                @error('email')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="{{ $formId }}-phone" class="label">{{ __('ui.lead.phone') }} <span class="normal-case tracking-normal">({{ __('ui.lead.optional') }})</span></label>
            <input id="{{ $formId }}-phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone') }}" class="field mt-2 @error('phone') border-error @enderror">
            @error('phone')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror
        </div>

        @if ($isValuation)
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="{{ $formId }}-address" class="label">{{ __('ui.lead.address') }}</label>
                    <input id="{{ $formId }}-address" name="payload[address]" type="text" autocomplete="street-address" value="{{ old('payload.address') }}" class="field mt-2">
                </div>
                <div>
                    <label for="{{ $formId }}-city" class="label">{{ __('ui.lead.city') }}</label>
                    <input id="{{ $formId }}-city" name="payload[city]" type="text" value="{{ old('payload.city') }}" class="field mt-2">
                </div>
                <div>
                    <label for="{{ $formId }}-ptype" class="label">{{ __('ui.lead.property_type') }}</label>
                    <input id="{{ $formId }}-ptype" name="payload[property_type]" type="text" value="{{ old('payload.property_type') }}" class="field mt-2">
                </div>
                <div>
                    <label for="{{ $formId }}-bedrooms" class="label">{{ __('ui.lead.bedrooms') }}</label>
                    <input id="{{ $formId }}-bedrooms" name="payload[bedrooms]" type="number" min="0" max="20" inputmode="numeric" value="{{ old('payload.bedrooms') }}" class="field mt-2">
                </div>
                <div>
                    <label for="{{ $formId }}-area" class="label">{{ __('ui.lead.area') }}</label>
                    <input id="{{ $formId }}-area" name="payload[area]" type="number" min="0" step="1" inputmode="decimal" value="{{ old('payload.area') }}" class="field mt-2">
                </div>
                <div class="sm:col-span-2">
                    <label for="{{ $formId }}-condition" class="label">{{ __('ui.lead.condition') }}</label>
                    <input id="{{ $formId }}-condition" name="payload[condition]" type="text" value="{{ old('payload.condition') }}" class="field mt-2">
                </div>
            </div>
            {{-- Estimativa que o simulador mostrou (se a pessoa passou por ele). --}}
            <input type="hidden" id="{{ $formId }}-estimate" name="payload[estimate]" value="{{ old('payload.estimate') }}">
        @endif

        <div>
            <label for="{{ $formId }}-message" class="label">{{ __('ui.lead.message') }}</label>
            <textarea id="{{ $formId }}-message" name="message" rows="4" class="field mt-2 @error('message') border-error @enderror">{{ old('message', $defaultMessage) }}</textarea>
            @error('message')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror
        </div>

        {{-- RGPD: dois consentimentos distintos, nunca pré-marcados. --}}
        <fieldset class="grid gap-3 text-sm">
            <legend class="sr-only">Consentimentos</legend>
            <label class="flex items-start gap-3">
                <input type="checkbox" name="consent_contact" value="1" @checked(old('consent_contact')) class="mt-1 h-5 w-5 shrink-0 accent-olive-600">
                <span>{{ __('ui.lead.consent_contact') }}</span>
            </label>
            <label class="flex items-start gap-3">
                <input type="checkbox" name="consent_marketing" value="1" @checked(old('consent_marketing')) class="mt-1 h-5 w-5 shrink-0 accent-olive-600">
                <span>{{ __('ui.lead.consent_marketing') }}</span>
            </label>
        </fieldset>

        <p class="text-xs text-ink-muted">
            {!! __('ui.lead.privacy_notice', ['name' => e(config('agency.name')), 'link' => '<a href="'.route('privacy').'" class="link">'.__('ui.lead.privacy_link').'</a>']) !!}
        </p>

        <div>
            <button type="submit" class="btn-primary">{{ __('ui.lead.submit') }}</button>
        </div>
    </form>
</div>
