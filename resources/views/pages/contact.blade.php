<x-layouts.app :title="__('ui.nav.contact')" :canonical="route('contact')">
    {{-- Abertura: só tipografia e espaço, com os contactos em tamanho de leitura. --}}
    <section class="container-site grid gap-12 pb-24 pt-20 sm:pt-28 lg:grid-cols-[3fr_2fr] lg:items-end">
        <x-site.reveal>
            <p class="eyebrow">{{ config('agency.name') }}</p>
            <h1 class="display mt-3">{{ __('ui.nav.contact') }}</h1>
        </x-site.reveal>
        <x-site.reveal atraso="150" class="lg:pb-3">
            <div class="space-y-3 font-serif text-xl">
                @if (config('agency.address'))<p>{{ config('agency.address') }}</p>@endif
                @if (config('agency.phone'))<p><a class="link" href="tel:{{ preg_replace('/\s+/', '', config('agency.phone')) }}">{{ config('agency.phone') }}</a></p>@endif
                @if (config('agency.email'))<p><a class="link" href="mailto:{{ config('agency.email') }}">{{ config('agency.email') }}</a></p>@endif
            </div>
            <p class="mt-6 max-w-sm text-sm leading-relaxed text-ink-muted">{{ __('ui.contact.note') }}</p>
        </x-site.reveal>
    </section>

    {{-- Faixa escura com o formulário, como na referência: campos só com uma linha. --}}
    <section class="band band-dark">
        <div class="container-site">
            <x-site.reveal class="mx-auto max-w-3xl">
                <x-lead-form source="contact" tone="linha" />
            </x-site.reveal>
        </div>
    </section>
</x-layouts.app>
