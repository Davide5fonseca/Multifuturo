<x-layouts.app :title="__('ui.zones.title')" :description="__('ui.zones.lead')" :canonical="route('zones.index')">
    <section class="container-site pt-16 pb-24">
        <p class="label">{{ config('agency.name') }}</p>
        <h1 class="mt-3 text-4xl sm:text-5xl">{{ __('ui.zones.title') }}</h1>
        <p class="mt-4 max-w-xl text-ink-muted">{{ __('ui.zones.lead') }}</p>

        @if ($cities->isEmpty())
            <p class="mt-12 text-ink-muted">{{ __('ui.listing.coming_soon') }}</p>
        @else
            <ul class="mt-12 grid gap-px overflow-hidden rounded-xl border border-sand-200 bg-sand-200 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cities as $c)
                    <li class="bg-sand-50">
                        <a href="{{ route('zones.city', $c['slug']) }}" class="group flex items-baseline justify-between gap-4 px-6 py-6 hover:bg-sand-100">
                            <span class="font-serif text-2xl group-hover:text-olive-700">{{ $c['name'] }}</span>
                            <span class="text-xs text-ink-muted">{{ trans_choice('ui.zones.properties_count', $c['count'], ['count' => $c['count']]) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.app>
