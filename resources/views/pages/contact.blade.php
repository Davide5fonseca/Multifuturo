<x-layouts.app :title="__('ui.nav.contact')" :canonical="route('contact')">
    {{-- Fase 5: página funcional. Dados da agência e layout final entram na Fase 4/6. --}}
    <section class="container-site grid gap-12 pt-16 pb-16 lg:grid-cols-2">
        <div>
            <p class="label">{{ config('agency.name') }}</p>
            <h1 class="mt-3 text-4xl sm:text-5xl">{{ __('ui.nav.contact') }}</h1>
            <div class="mt-8 space-y-2 text-ink-muted">
                @if (config('agency.address'))<p>{{ config('agency.address') }}</p>@endif
                @if (config('agency.phone'))<p><a class="link" href="tel:{{ preg_replace('/\s+/', '', config('agency.phone')) }}">{{ config('agency.phone') }}</a></p>@endif
                @if (config('agency.email'))<p><a class="link" href="mailto:{{ config('agency.email') }}">{{ config('agency.email') }}</a></p>@endif
            </div>
        </div>
        <x-lead-form source="contact" />
    </section>
</x-layouts.app>
