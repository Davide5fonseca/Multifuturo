{{--
    Formulário de lead — um componente para as três origens:
      source="property"   ficha de imóvel (passar :property)
      source="contact"    contacto geral
      source="valuation"  "Quanto vale a minha casa?" — os dados do imóvel vêm do
                          simulador ao lado (campos escondidos payload[...], sempre
                          sincronizados); o visitante só deixa o contacto e a morada

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
        {{--
            O simulador (mesma página) emite 'valuation-change' a cada alteração e
            'valuation-estimate' quando se carrega em "Pedir avaliação". Os campos
            escondidos acompanham sempre o simulador; o botão ainda propõe a
            mensagem e leva a pessoa ao formulário.
        --}}
        x-data="{
            sync(d) {
                const set = (n, v) => { const el = document.getElementById('{{ $formId }}-' + n); if (el) el.value = v ?? ''; };
                set('city', d.city); set('locality', d.locality); set('ptype', d.type); set('area', d.area); set('condition', d.condition); set('estimate', d.estimate);
            },
            request(d) {
                this.sync(d);
                const m = document.getElementById('{{ $formId }}-message'); if (m && ! m.value.trim()) m.value = d.message;
                document.getElementById('{{ $formId }}-name')?.focus({ preventScroll: true });
            },
        }"
        x-on:valuation-change.window="sync($event.detail)"
        x-on:valuation-estimate.window="request($event.detail)"
    @endif
>
    <h2 class="text-2xl">{{ __('ui.lead.'.($isValuation ? 'form_title_valuation' : 'title_'.$source)) }}</h2>
    <p class="mt-2 text-sm text-ink-muted">{{ __('ui.lead.'.($isValuation ? 'form_lead_valuation' : 'lead_'.$source)) }}</p>

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
            <div>
                <label for="{{ $formId }}-address" class="label">{{ __('ui.lead.address') }} <span class="normal-case tracking-normal">({{ __('ui.lead.optional') }})</span></label>
                <input id="{{ $formId }}-address" name="payload[address]" type="text" autocomplete="street-address" value="{{ old('payload.address') }}" class="field mt-2">
            </div>
            {{-- O imóvel descreve-se no simulador ao lado; estes campos seguem-no em silêncio. --}}
            <input type="hidden" id="{{ $formId }}-city" name="payload[city]" value="{{ old('payload.city') }}">
            <input type="hidden" id="{{ $formId }}-locality" name="payload[locality]" value="{{ old('payload.locality') }}">
            <input type="hidden" id="{{ $formId }}-ptype" name="payload[property_type]" value="{{ old('payload.property_type') }}">
            <input type="hidden" id="{{ $formId }}-area" name="payload[area]" value="{{ old('payload.area') }}">
            <input type="hidden" id="{{ $formId }}-condition" name="payload[condition]" value="{{ old('payload.condition') }}">
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
