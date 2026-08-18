<x-layouts.app :title="$title" :canonical="url()->current()">
    {{-- Fase 1: esqueleto. O componente Livewire de listagem com filtros substitui este bloco na Fase 4. --}}
    <section class="container-site pt-16 pb-16">
        <p class="label">{{ $businessType === 'sale' ? __('ui.nav.buy') : __('ui.nav.rent') }}</p>
        <h1 class="mt-3 text-4xl sm:text-5xl">{{ $title }}</h1>
        <p class="mt-6 max-w-xl text-ink-muted">{{ __('ui.listing.coming_soon') }}</p>
    </section>
</x-layouts.app>
