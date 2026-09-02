{{-- 410 Gone: o imóvel saiu do feed. Sem dados do imóvel (já não está publicado); só semelhantes e contacto. --}}
<x-layouts.app :title="__('ui.property.gone_title')" robots="noindex,follow">
    <section class="container-site pb-24 pt-20 sm:pt-28">
        <p class="label">{{ $property->reference ?? $property->internal_id }}</p>
        <h1 class="mt-3 max-w-2xl text-4xl sm:text-5xl">{{ __('ui.property.gone_title') }}</h1>
        <p class="mt-6 max-w-xl text-ink-muted">{{ __('ui.property.gone_lead') }}</p>
        <div class="mt-8 flex flex-wrap gap-4">
            <a href="{{ route($property->business_type->routeName(), array_filter(['concelho' => $property->city])) }}" class="btn-primary">{{ __('ui.property.back_to_list') }}</a>
            <a href="{{ route('contact') }}" class="btn-secondary">{{ __('ui.nav.contact') }}</a>
        </div>

        @if ($similar->isNotEmpty())
            <h2 class="mt-20 text-3xl">{{ __('ui.property.similar') }}</h2>
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($similar as $s)
                    <x-property.card :property="$s" />
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
