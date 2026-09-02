<x-layouts.app :title="__('ui.zones.title')" :description="__('ui.zones.lead')" :canonical="route('zones.index')">
    <section class="container-site pb-24 pt-20 sm:pt-28">
        <x-site.reveal>
            <p class="eyebrow">{{ config('agency.name') }}</p>
            <h1 class="display-sm mt-3">{{ __('ui.zones.title') }}</h1>
        </x-site.reveal>
        <p class="mt-4 max-w-xl text-ink-muted">{{ __('ui.zones.lead') }}</p>

        @if ($cities->isEmpty())
            <p class="mt-12 text-ink-muted">{{ __('ui.listing.coming_soon') }}</p>
        @else
            <ul class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cities as $c)
                    <li class="overflow-hidden rounded-xl border border-sand-200 bg-sand-50">
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
