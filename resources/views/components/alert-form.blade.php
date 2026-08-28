{{--
    "Avise-me quando entrar um imóvel assim" — na listagem, por baixo dos
    filtros. Leva os critérios ativos em campos escondidos (o Livewire
    volta a renderizar isto a cada mudança de filtro, por isso estão sempre
    certos), pede só o email e o consentimento, e o resto é igual às leads:
    honeypot, tempo mínimo, versão da política. Nada é enviado sem a pessoa
    confirmar o email (double opt-in).
--}}
@props(['listing', 'criteria' => []])

@php
    $formId = 'alert-'.$listing;
    $summary = \App\Support\PropertyFilters::summary($criteria, $listing);
@endphp

<div id="{{ $formId }}" class="mt-8 rounded-xl border border-sand-200 bg-sand-100 p-5">
    <h2 class="text-lg">{{ __('ui.alerts.title') }}</h2>
    <p class="mt-1 text-sm text-ink-muted">{{ __('ui.alerts.lead') }}</p>
    <p class="mt-3 text-sm font-medium" data-alert-summary>{{ $summary }}</p>

    @if (session('alert_sent'))
        <p class="mt-4 border-l-2 border-olive-600 bg-sand-50 px-4 py-3 text-sm" role="status">{{ __('ui.alerts.sent') }}</p>
    @elseif (session('alert_exists'))
        <p class="mt-4 border-l-2 border-olive-600 bg-sand-50 px-4 py-3 text-sm" role="status">{{ __('ui.alerts.exists') }}</p>
    @endif

    @if ($errors->alerts->any())
        <p class="mt-4 border-l-2 border-error bg-sand-50 px-4 py-3 text-sm text-error" role="alert">{{ $errors->alerts->first() }}</p>
    @endif

    <form method="post" action="{{ route('alerts.store') }}" class="mt-4 grid gap-4" novalidate>
        @csrf
        <input type="hidden" name="listing" value="{{ $listing }}">
        <input type="hidden" name="form_ts" value="{{ \App\Http\Requests\StoreAlertRequest::signedTimestamp() }}">
        @foreach ($criteria as $key => $value)
            @if (is_array($value))
                @foreach ($value as $item)
                    <input type="hidden" name="criteria[{{ $key }}][]" value="{{ $item }}">
                @endforeach
            @else
                <input type="hidden" name="criteria[{{ $key }}]" value="{{ $value }}">
            @endif
        @endforeach

        {{-- Honeypot: invisível para humanos; bots preenchem. --}}
        <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
            <label for="{{ $formId }}-website">Website</label>
            <input type="text" id="{{ $formId }}-website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <div>
            <label for="{{ $formId }}-email" class="label">{{ __('ui.lead.email') }}</label>
            <input id="{{ $formId }}-email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}" class="field mt-2 py-2 text-sm @error('email', 'alerts') border-error @enderror">
        </div>

        <div class="flex items-start gap-3 text-sm">
            <input type="checkbox" id="{{ $formId }}-consent" name="consent" value="1" @checked(old('consent')) class="mt-0.5 h-5 w-5 shrink-0 accent-olive-600">
            <label for="{{ $formId }}-consent">{{ __('ui.alerts.consent') }}</label>
        </div>

        <p class="text-xs text-ink-muted">
            {!! __('ui.alerts.privacy_notice', ['name' => e(config('agency.name')), 'link' => '<a href="'.route('privacy').'" class="link">'.__('ui.lead.privacy_link').'</a>']) !!}
        </p>

        <div>
            <button type="submit" class="btn-primary py-2">{{ __('ui.alerts.submit') }}</button>
        </div>
    </form>
</div>
