@php
    use App\Support\Format;
    $p = $property;
    $title = $p->title ?: trim(($p->property_type ?? '').' '.(Format::typology($p->bedrooms) ?? ''));
    $location = Format::location($p->locality, $p->city, $p->district);
    $metaTitle = $title.($location ? " — {$location}" : '').' · '.($p->reference ?? $p->internal_id);
    $metaDescription = $p->description ? mb_substr(preg_replace('/\s+/', ' ', strip_tags($p->description)), 0, 155) : ($title.', '.$location.'. '.Format::price($p->price, $p->currency, $p->business_type));
    $details = array_filter([
        __('ui.property.type') => $p->property_type,
        __('ui.property.bedrooms') => Format::typology($p->bedrooms),
        __('ui.property.bathrooms') => $p->bathrooms,
        __('ui.property.house_area') => Format::area($p->house_area),
        __('ui.property.gross_area') => Format::area($p->gross_area),
        __('ui.property.plot_area') => Format::area($p->plot_area),
        __('ui.property.floor') => $p->floor_number,
        __('ui.property.build_year') => $p->build_year,
        __('ui.property.condition') => $p->property_condition,
        __('ui.property.energy_rating') => $p->energy_rating,
        __('ui.property.zipcode') => $p->zipcode,
    ], fn ($v) => $v !== null && $v !== '');
    $coords = $p->coordinates;
@endphp
<x-layouts.app :title="$metaTitle" :description="$metaDescription" :canonical="route('property.show', $p)" :image="$p->cover_photo['url'] ?? null">
    <x-slot:head>
        <meta property="og:type" content="product">
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    </x-slot:head>

    <article class="container-site pt-8 pb-24">
        {{-- Migalhas discretas --}}
        <nav aria-label="Breadcrumb" class="text-xs text-ink-muted">
            <ol class="flex flex-wrap gap-2">
                <li><a href="{{ route('home') }}" class="hover:text-ink">Início</a></li>
                <li aria-hidden="true">/</li>
                <li><a href="{{ route($p->business_type->routeName()) }}" class="hover:text-ink">{{ $p->business_type === \App\Enums\BusinessType::Sale ? __('ui.nav.buy') : __('ui.nav.rent') }}</a></li>
                @if ($p->city)
                    <li aria-hidden="true">/</li>
                    <li><a href="{{ route('zones.city', \Illuminate\Support\Str::slug($p->city)) }}" class="hover:text-ink">{{ $p->city }}</a></li>
                @endif
                <li aria-hidden="true">/</li>
                <li aria-current="page">{{ $p->reference ?? $p->internal_id }}</li>
            </ol>
        </nav>

        <div class="mt-6">
            <x-property.gallery :photos="$p->photos ?? []" :title="$title" />
        </div>

        <div class="mt-12 grid gap-12 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div>
                <header>
                    <p class="label">
                        {{ __('ui.property.reference') }} {{ $p->reference ?? $p->internal_id }} · {{ $p->business_type->label() }}
                        @if ($p->is_exclusive) · <span class="text-olive-700">{{ __('ui.property.exclusive') }}</span> @endif
                    </p>
                    <h1 class="mt-3 text-3xl leading-tight sm:text-5xl">{{ $title }}</h1>
                    <p class="mt-3 text-ink-muted">{{ $location }}</p>
                    <p class="price mt-6 text-3xl sm:text-4xl">{{ Format::price($p->price, $p->currency, $p->business_type) }}</p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <button type="button" x-cloak x-data @click="$store.favorites.toggle(@js($p->slug))" class="btn-secondary py-2 text-xs"
                                :aria-pressed="$store.favorites.has(@js($p->slug))">
                            <span x-text="$store.favorites.has(@js($p->slug)) ? @js(__('ui.property.favorite_remove')) : @js(__('ui.property.favorite_add'))"></span>
                        </button>
                        @if ($p->virtual_tour_url)
                            <a href="{{ $p->virtual_tour_url }}" rel="noopener nofollow" target="_blank" class="btn-secondary py-2 text-xs">{{ __('ui.property.virtual_tour') }}</a>
                        @endif
                        @if ($p->video_url)
                            <a href="{{ $p->video_url }}" rel="noopener nofollow" target="_blank" class="btn-secondary py-2 text-xs">{{ __('ui.property.video') }}</a>
                        @endif
                        @if ($p->floorplan_url)
                            <a href="{{ $p->floorplan_url }}" rel="noopener nofollow" target="_blank" class="btn-secondary py-2 text-xs">{{ __('ui.property.floorplan') }}</a>
                        @endif
                    </div>
                </header>

                @if ($details)
                    <section class="mt-12">
                        <h2 class="label">{{ __('ui.property.details') }}</h2>
                        <dl class="mt-4 grid grid-cols-2 gap-x-8 gap-y-4 border-t border-sand-200 pt-4 text-sm sm:grid-cols-3">
                            @foreach ($details as $label => $value)
                                <div>
                                    <dt class="text-ink-muted">{{ $label }}</dt>
                                    <dd class="mt-0.5 font-medium">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endif

                @if ($p->description)
                    <section class="mt-12">
                        <h2 class="label">{{ __('ui.property.description') }}</h2>
                        <div class="prose-multifuturo mt-4 max-w-2xl text-ink/90">
                            {!! nl2br(e($p->description)) !!}
                        </div>
                    </section>
                @endif

                @if ($p->features)
                    <section class="mt-12">
                        <h2 class="label">{{ __('ui.property.features') }}</h2>
                        <ul class="mt-4 grid grid-cols-2 gap-x-8 gap-y-2 text-sm sm:grid-cols-3">
                            @foreach ($p->features as $feature)
                                <li class="flex items-center gap-2 capitalize"><span class="h-1.5 w-1.5 bg-olive-600" aria-hidden="true"></span>{{ $feature }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Mapa: só se gmap_visible (compromisso contratual). O iframe do OSM só é criado ao clicar — zero pedidos externos até lá. --}}
                <section class="mt-12">
                    <h2 class="label">{{ __('ui.property.map') }}</h2>
                    @if ($coords)
                        @php
                            $lat = (float) $coords['lat']; $lon = (float) $coords['lon']; $d = 0.008;
                            $osm = sprintf('https://www.openstreetmap.org/export/embed.html?bbox=%F,%F,%F,%F&layer=mapnik&marker=%F,%F', $lon - $d, $lat - $d, $lon + $d, $lat + $d, $lat, $lon);
                        @endphp
                        <div x-data="{ show: false }" class="mt-4 border border-sand-200 bg-sand-100">
                            <div x-show="!show" class="flex flex-col items-start gap-3 p-6">
                                <p class="text-sm text-ink-muted">{{ __('ui.property.map_notice') }}</p>
                                <button type="button" class="btn-secondary py-2 text-xs" @click="show = true">{{ __('ui.property.show_map') }}</button>
                                <noscript><a class="link text-sm" href="https://www.openstreetmap.org/?mlat={{ $lat }}&mlon={{ $lon }}#map=16/{{ $lat }}/{{ $lon }}" rel="noopener" target="_blank">OpenStreetMap</a></noscript>
                            </div>
                            <template x-if="show">
                                <iframe src="{{ $osm }}" title="{{ __('ui.property.map') }}" class="block h-96 w-full" loading="lazy" referrerpolicy="no-referrer"></iframe>
                            </template>
                        </div>
                    @else
                        <p class="mt-4 text-sm text-ink-muted">{{ __('ui.property.map_hidden') }}</p>
                    @endif
                </section>
            </div>

            <aside class="lg:sticky lg:top-8 lg:self-start">
                @if ($p->broker && ($p->broker['name'] ?? null))
                    <div class="mb-6 flex items-center gap-4">
                        @if ($p->broker['photo'] ?? null)
                            <img src="{{ $p->broker['photo'] }}" alt="" width="56" height="56" class="h-14 w-14 rounded-full object-cover" loading="lazy" data-fallback="{{ asset('images/placeholder-property.jpg') }}">
                        @endif
                        <div>
                            <p class="label">{{ __('ui.property.contact_broker') }}</p>
                            <p class="mt-1 font-medium">{{ $p->broker['name'] }}</p>
                        </div>
                    </div>
                @endif
                <x-lead-form source="property" :property="$p" />
            </aside>
        </div>

        @if ($similar->isNotEmpty())
            <section class="mt-24">
                <div class="flex items-end justify-between gap-6">
                    <h2 class="text-3xl">{{ __('ui.property.similar') }}</h2>
                    <a href="{{ route($p->business_type->routeName(), ['concelho' => $p->city]) }}" class="link text-sm">{{ __('ui.property.back_to_list') }}</a>
                </div>
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($similar as $s)
                        <x-property.card :property="$s" />
                    @endforeach
                </div>
            </section>
        @endif
    </article>
</x-layouts.app>
