<x-layouts.app :title="__('ui.lead.title_valuation')" :canonical="route('valuation')">
    {{--
        Lead magnet: à esquerda a estimativa imediata (conta no browser, sem
        pedidos), à direita o pedido de avaliação. O botão do simulador
        preenche o formulário com os dados e a estimativa vista.
    --}}
    <section class="container-site grid gap-12 pt-16 pb-16 lg:grid-cols-2">
        <div>
            <p class="label">{{ __('ui.nav.valuation') }}</p>
            <h1 class="mt-3 text-4xl sm:text-5xl">{{ __('ui.lead.title_valuation') }}</h1>
            <p class="mt-6 max-w-md text-ink-muted">{{ __('ui.lead.lead_valuation') }}</p>

            <div class="mt-12">
                <h2 class="label">{{ __('ui.valuation.title') }}</h2>
                <p class="mt-2 max-w-md text-sm text-ink-muted">{{ __('ui.valuation.lead') }}</p>
                <div class="mt-4"><x-valuation-simulator :table="$table" /></div>
            </div>
        </div>
        <x-lead-form source="valuation" class="lg:sticky lg:top-8 lg:self-start" />
    </section>
</x-layouts.app>
