<x-layouts.app :title="__('ui.alerts.'.$state.'_title')" robots="noindex,nofollow">
    {{-- Destino das ligações dos emails de alerta: confirmado, cancelado ou ligação inválida. --}}
    <section class="container-site pt-16 pb-24">
        <div class="max-w-xl">
            <p class="label">{{ __('ui.alerts.title') }}</p>
            <h1 class="mt-3 text-4xl sm:text-5xl">{{ __('ui.alerts.'.$state.'_title') }}</h1>
            <p class="mt-6 text-ink-muted">{{ __('ui.alerts.'.$state.'_lead') }}</p>

            @if ($alert)
                <p class="mt-4 rounded-xl border border-sand-200 bg-sand-100 px-5 py-4 text-sm font-medium">{{ $alert->summary() }}</p>
            @endif

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ $alert ? $alert->listingUrl() : route('buy') }}" class="btn-primary">{{ __('ui.alerts.see_listing') }}</a>
                @if ($state === 'unsubscribed' && $alert)
                    <a href="{{ \Illuminate\Support\Facades\URL::signedRoute('alerts.confirm', ['locale' => $alert->locale, 'token' => $alert->token]) }}" class="btn-secondary">{{ __('ui.alerts.reactivate') }}</a>
                @endif
            </div>
        </div>
    </section>
</x-layouts.app>
