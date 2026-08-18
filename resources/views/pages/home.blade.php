<x-layouts.app :title="__('ui.home.title')" :description="__('ui.home.lead')" :canonical="route('home')">
    {{-- Fase 1: só estrutura e tom. Destaques, pesquisa rápida e zonas entram na Fase 4. --}}
    <section class="container-site pt-20 pb-16 sm:pt-28">
        <p class="label">{{ __('ui.home.eyebrow') }}</p>
        <h1 class="mt-4 max-w-3xl text-4xl leading-tight sm:text-6xl">{{ __('ui.home.title') }}</h1>
        <p class="mt-6 max-w-xl text-lg text-ink-muted">{{ __('ui.home.lead') }}</p>

        <x-site.search-form class="mt-12 max-w-2xl" />

        <div class="mt-10 flex flex-wrap gap-4">
            <a href="{{ route('buy') }}" class="btn-primary">{{ __('ui.nav.buy') }}</a>
            <a href="{{ route('rent') }}" class="btn-secondary">{{ __('ui.nav.rent') }}</a>
        </div>
    </section>
</x-layouts.app>
