<x-layouts.app :title="__('ui.lead.title_valuation')" :canonical="route('valuation')">
    {{--
        Um só cartão, lido como um fluxo: 1 · o imóvel (simulador, com a
        estimativa a aparecer) → 2 · os dados de contacto → um único botão.
        A introdução fica à esquerda, fixa. O simulador entra no formulário
        pelo slot "simulator"; a conta faz-se no browser, sem pedidos.
    --}}
    <section class="container-site grid gap-12 pb-16 pt-20 sm:pt-28 lg:grid-cols-[2fr_3fr]">
        <div class="lg:sticky lg:top-8 lg:self-start">
            <x-site.reveal>
                <p class="eyebrow">{{ __('ui.nav.valuation') }}</p>
                <h1 class="display-sm mt-3">{{ __('ui.lead.title_valuation') }}</h1>
            </x-site.reveal>
            <p class="mt-6 max-w-md text-ink-muted">{{ __('ui.lead.lead_valuation') }}</p>
            <p class="mt-4 max-w-md text-sm text-ink-muted">{{ __('ui.valuation.lead') }}</p>
        </div>
        <x-lead-form source="valuation">
            <x-slot:simulator>
                <x-valuation-simulator :table="$table" />
            </x-slot:simulator>
        </x-lead-form>
    </section>
</x-layouts.app>
