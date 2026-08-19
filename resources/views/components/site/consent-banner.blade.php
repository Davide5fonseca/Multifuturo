{{--
    Banner de cookies com consentimento granular. Aparece só quando não há
    escolha guardada (ou a versão mudou). "Recusar não essenciais" tem o mesmo
    peso visual que "Aceitar tudo" — recusa efetiva, sem dark patterns.
    Sem JS não há scripts não essenciais a ativar, logo o banner não é preciso (x-cloak).
--}}
<div x-data x-cloak x-show="$store.consent.open" x-transition.opacity
     class="fixed inset-x-0 bottom-0 z-40 border-t border-sand-200 bg-sand-50 text-ink"
     role="dialog" aria-modal="false" aria-labelledby="consent-title" aria-describedby="consent-text">
    <div class="container-site py-6">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
            <div>
                <h2 id="consent-title" class="font-sans text-base font-medium">{{ __('legal.consent.title') }}</h2>
                <p id="consent-text" class="mt-1 max-w-3xl text-sm text-ink-muted">
                    {!! __('legal.consent.text', ['link' => '<a href="'.route('cookies').'" class="link">'.__('legal.consent.link').'</a>']) !!}
                </p>
            </div>
            <div class="flex flex-wrap gap-3" x-show="!$store.consent.customizing">
                <button type="button" class="btn-secondary py-2 text-xs" @click="$store.consent.manage()">{{ __('legal.consent.customize') }}</button>
                <button type="button" class="btn-secondary py-2 text-xs" @click="$store.consent.rejectAll()">{{ __('legal.consent.reject_all') }}</button>
                <button type="button" class="btn-primary py-2 text-xs" @click="$store.consent.acceptAll()">{{ __('legal.consent.accept_all') }}</button>
            </div>
        </div>

        <fieldset x-show="$store.consent.customizing" x-cloak class="mt-6 grid gap-4 border-t border-sand-200 pt-6 sm:grid-cols-3">
            <legend class="sr-only">{{ __('legal.consent.customize') }}</legend>

            <div class="text-sm">
                <p class="flex items-center justify-between font-medium">
                    {{ __('legal.consent.necessary') }}
                    <span class="label">{{ __('legal.consent.always_on') }}</span>
                </p>
                <p class="mt-1 text-ink-muted">{{ __('legal.consent.necessary_desc') }}</p>
            </div>

            <label class="text-sm">
                <span class="flex items-center justify-between font-medium">
                    {{ __('legal.consent.analytics') }}
                    <input type="checkbox" x-model="$store.consent.choices.analytics" aria-label="{{ __('legal.consent.analytics') }}" class="h-4 w-4 accent-olive-600">
                </span>
                <span class="mt-1 block text-ink-muted">{{ __('legal.consent.analytics_desc') }}</span>
            </label>

            <label class="text-sm">
                <span class="flex items-center justify-between font-medium">
                    {{ __('legal.consent.marketing') }}
                    <input type="checkbox" x-model="$store.consent.choices.marketing" aria-label="{{ __('legal.consent.marketing') }}" class="h-4 w-4 accent-olive-600">
                </span>
                <span class="mt-1 block text-ink-muted">{{ __('legal.consent.marketing_desc') }}</span>
            </label>

            <div class="flex flex-wrap gap-3 sm:col-span-3">
                <button type="button" class="btn-primary py-2 text-xs" @click="$store.consent.saveChoices()">{{ __('legal.consent.save') }}</button>
                <button type="button" class="btn-secondary py-2 text-xs" @click="$store.consent.rejectAll()">{{ __('legal.consent.reject_all') }}</button>
            </div>
        </fieldset>
    </div>
</div>
