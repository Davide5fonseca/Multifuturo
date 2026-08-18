<x-layouts.app :title="__('ui.lead.title_valuation')" :canonical="route('valuation')">
    {{-- Lead magnet separado da ficha de imóvel: entra no CRM como lead sem imóvel associado. --}}
    <section class="container-site grid gap-12 pt-16 pb-16 lg:grid-cols-2">
        <div>
            <p class="label">{{ __('ui.nav.valuation') }}</p>
            <h1 class="mt-3 text-4xl sm:text-5xl">{{ __('ui.lead.title_valuation') }}</h1>
            <p class="mt-6 max-w-md text-ink-muted">{{ __('ui.lead.lead_valuation') }}</p>
        </div>
        <x-lead-form source="valuation" />
    </section>
</x-layouts.app>
