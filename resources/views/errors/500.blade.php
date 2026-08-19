{{--
    500 — erro do nosso lado. A página é deliberadamente independente de tudo o
    que possa estar avariado: sem Livewire, sem chamadas à BD, só o layout base.
    Dá saídas úteis (recarregar, início, contactos) em vez de um beco.
--}}
<x-layouts.app :title="__('ui.errors.500_title')" robots="noindex,nofollow">
    <section class="container-site pt-24 pb-16">
        <p class="label">500</p>
        <h1 class="mt-3 max-w-2xl text-4xl sm:text-5xl">{{ __('ui.errors.500_title') }}</h1>
        <p class="mt-6 max-w-xl text-ink-muted">{{ __('ui.errors.500_lead') }}</p>
        <div class="mt-10 flex flex-wrap gap-4">
            <a href="" class="btn-primary">{{ __('ui.errors.retry') }}</a>
            <a href="{{ route('home') }}" class="btn-secondary">{{ __('ui.errors.404_back') }}</a>
            <a href="{{ route('contact') }}" class="btn-secondary">{{ __('ui.nav.contact') }}</a>
        </div>
        @if (config('agency.phone') || config('agency.email'))
            <p class="mt-8 text-sm text-ink-muted">
                {{ __('ui.errors.500_direct') }}
                @if (config('agency.phone')) <a class="link" href="tel:{{ preg_replace('/\s+/', '', config('agency.phone')) }}">{{ config('agency.phone') }}</a> @endif
                @if (config('agency.phone') && config('agency.email')) · @endif
                @if (config('agency.email')) <a class="link" href="mailto:{{ config('agency.email') }}">{{ config('agency.email') }}</a> @endif
            </p>
        @endif
    </section>
</x-layouts.app>
