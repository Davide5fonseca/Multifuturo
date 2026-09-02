@php
    $title = $editorial?->title ?: __('ui.zones.in_zone', ['zone' => $zoneName]);
    $description = $editorial?->meta_description ?: ($editorial?->intro ? mb_substr($editorial->intro, 0, 155) : __('ui.zones.in_zone', ['zone' => $zoneName]).' — '.config('agency.name'));
@endphp
<x-layouts.app :title="$title" :description="$description" :canonical="url()->current()" :image="$editorial?->cover_url">
    <section class="container-site pt-8 pb-24">
        <nav aria-label="Breadcrumb" class="text-xs text-ink-muted">
            <ol class="flex flex-wrap gap-2">
                <li><a href="{{ route('home') }}" class="hover:text-ink">Início</a></li>
                <li aria-hidden="true">/</li>
                <li><a href="{{ route('zones.index') }}" class="hover:text-ink">{{ __('ui.zones.title') }}</a></li>
                @if ($localityName)
                    <li aria-hidden="true">/</li>
                    <li><a href="{{ route('zones.city', $citySlug) }}" class="hover:text-ink">{{ $cityName }}</a></li>
                @endif
                <li aria-hidden="true">/</li>
                <li aria-current="page">{{ $localityName ?? $cityName }}</li>
            </ol>
        </nav>

        <header class="mt-8 max-w-3xl">
            <x-site.reveal>
                <p class="eyebrow">{{ __('ui.zones.title') }}</p>
                <h1 class="display-sm mt-3">{{ $title }}</h1>
            </x-site.reveal>
            @if ($editorial?->intro)
                <p class="mt-6 text-lg text-ink-muted">{{ $editorial->intro }}</p>
            @endif
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('buy', array_filter(['concelho' => $cityName, 'freguesia' => $localityName])) }}" class="btn-primary py-2 text-xs">{{ __('ui.zones.buy_in', ['zone' => $localityName ?? $cityName]) }}</a>
                <a href="{{ route('rent', array_filter(['concelho' => $cityName, 'freguesia' => $localityName])) }}" class="btn-secondary py-2 text-xs">{{ __('ui.zones.rent_in', ['zone' => $localityName ?? $cityName]) }}</a>
            </div>
        </header>

        @if ($editorial?->cover_url)
            <div class="mt-10 overflow-hidden rounded-xl bg-sand-200">
                <img src="{{ $editorial->cover_url }}" alt="{{ $zoneName }}" width="1600" height="700" class="h-auto w-full object-cover" style="aspect-ratio: 16/7" loading="lazy" data-fallback="{{ asset('images/placeholder-property.jpg') }}">
            </div>
        @endif

        @if ($editorial?->body)
            <div class="mt-10 max-w-2xl space-y-4 text-ink/90">
                @foreach (preg_split('/\n\s*\n/', trim($editorial->body)) as $paragraph)
                    <p>{{ trim($paragraph) }}</p>
                @endforeach
            </div>
        @endif

        @if ($localities->isNotEmpty())
            <section class="mt-14">
                <h2 class="label">{{ __('ui.zones.localities') }}</h2>
                <ul class="mt-4 flex flex-wrap gap-2">
                    @foreach ($localities as $l)
                        <li><a href="{{ route('zones.locality', [$citySlug, $l['slug']]) }}" class="inline-block rounded-full border border-sand-200 px-4 py-1.5 text-sm hover:border-olive-600 hover:text-olive-700">{{ $l['name'] }} <span class="text-ink-muted">({{ $l['count'] }})</span></a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="mt-14">
            <div class="flex items-end justify-between gap-6">
                <h2 class="text-3xl">{{ isset($localityName) ? __('ui.zones.in_zone', ['zone' => $localityName]) : __('ui.zones.portfolio') }}</h2>
                <p class="text-sm text-ink-muted">{{ trans_choice('ui.zones.properties_count', $properties->total(), ['count' => $properties->total()]) }}</p>
            </div>
            @if ($properties->isEmpty())
                <p class="mt-8 text-ink-muted">{{ __('ui.listing.empty') }}</p>
            @else
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($properties as $i => $property)
                        <x-property.card :property="$property" :eager="$i < 3" />
                    @endforeach
                </div>
                <div class="mt-12">{{ $properties->onEachSide(1)->links('pagination.multifuturo') }}</div>
            @endif
        </section>
    </section>
</x-layouts.app>
