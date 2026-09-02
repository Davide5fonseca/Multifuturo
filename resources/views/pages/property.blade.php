@php
    use App\Support\Format;
    $p = $property;
    $title = $p->title ?: trim(($p->property_type ?? '').' '.(Format::typology($p->bedrooms) ?? ''));
    $tipo = $p->property_type ?: $title;
    $location = Format::location($p->locality, $p->city, $p->district);
    $metaTitle = $title.($location ? " — {$location}" : '').' · '.($p->reference ?? $p->internal_id);
    // Meta description: a Descrição SEO escrita à mão → descrição curta → início da descrição → dados da ficha.
    $texto = $p->description ?: strip_tags((string) $p->website_html);
    $metaDescription = $p->seo_description
        ?: ($p->short_description
        ?: ($texto ? mb_substr(preg_replace('/\s+/', ' ', strip_tags($texto)), 0, 155) : ($title.', '.$location.'. '.Format::price($p->price, $p->currency, $p->business_type, $p->price_visible))));

    // Cartão lateral, pela ordem da referência: Tipologia, Quarto(s), WCs, áreas, ano, certificado.
    $tipologia = ($p->typology && $p->typology !== 'Não aplicável') ? $p->typology : Format::typology($p->bedrooms);
    $ficha = array_filter([
        __('ui.property.typology') => $tipologia,
        __('ui.property.bedrooms_count') => $p->bedrooms,
        __('ui.property.wc') => $p->bathrooms,
        __('ui.property.house_area') => Format::area($p->house_area),
        __('ui.property.gross_area') => Format::area($p->gross_area),
        __('ui.property.plot_area') => Format::area($p->plot_area),
        __('ui.property.floor') => $p->floor_number,
        __('ui.property.build_year') => $p->build_year,
        __('ui.property.condition') => $p->property_condition,
    ], fn ($v) => $v !== null && $v !== '');

    $ami = config('agency.ami');
    $coords = $p->coordinates;
@endphp
<x-layouts.app :title="$metaTitle" :description="$metaDescription" :keywords="$p->keywords()" :canonical="route('property.show', $p)" :image="$p->coverPhotoUrl()">
    <x-slot:head>
        <meta property="og:type" content="product">
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    </x-slot:head>

    {{-- A ficha regista-se nos "vistos recentemente" do visitante (localStorage). --}}
    <article class="container-site pt-8 pb-24" x-data x-init="$store.recent.push(@js($p->slug))">
        {{-- Migalhas discretas --}}
        <nav aria-label="Breadcrumb" class="text-xs text-ink-muted print:hidden">
            <ol class="flex flex-wrap gap-2">
                <li><a href="{{ route('home') }}" class="hover:text-ink">Início</a></li>
                <li aria-hidden="true">/</li>
                <li><a href="{{ route($p->business_type->routeName()) }}" class="hover:text-ink">{{ $p->business_type->routeName() === 'buy' ? __('ui.nav.buy') : __('ui.nav.rent') }}</a></li>
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

        {{--
            Cabeçalho da ficha, como na referência: tipo e concelho à esquerda,
            o tipo de negócio e a freguesia; preço e referência à direita; linha a fechar.
        --}}
        <header class="mt-10 flex flex-wrap items-start justify-between gap-x-10 gap-y-6 border-b border-sand-200 pb-6">
            <div>
                <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                    <h1 class="text-3xl tracking-tight sm:text-4xl">{{ $tipo }}</h1>
                    @if ($p->city)
                        <span class="text-lg font-medium">{{ $p->city }}</span>
                    @endif
                </div>
                <p class="label mt-2">
                    {{ $p->business_type->label() }}
                    @if ($location && $location !== $p->city) · {{ $location }} @endif
                    @if ($p->is_exclusive) · <span class="text-olive-700">{{ __('ui.property.exclusive') }}</span> @endif
                </p>
            </div>
            <div class="text-right">
                <p class="price text-3xl sm:text-4xl">{{ Format::price($p->price, $p->currency, $p->business_type, $p->price_visible) }}</p>
                <p class="mt-1 text-sm text-ink-muted">{{ __('ui.property.reference') }} {{ $p->reference ?? $p->internal_id }}</p>
            </div>
        </header>

        {{--
            Em ecrã largo: texto à esquerda (duas linhas da grelha), cartão de dados e
            pedido de informação à direita. Em telemóvel a ordem é cartão → texto →
            pedido, para os dados essenciais ficarem logo a seguir ao cabeçalho.
        --}}
        <div class="mt-10 grid gap-x-12 gap-y-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="order-2 lg:order-none lg:col-start-1 lg:row-start-1 lg:row-span-2">
                <h2 class="text-2xl font-semibold leading-snug tracking-tight">{{ $title }}</h2>

                @if ($p->website_html || $p->description)
                    <div class="prose-multifuturo mt-5 max-w-2xl text-[15px] leading-relaxed text-ink/90">
                        {{-- O texto "Website (HTML)" manda quando existe; passa pelo limpador — só formatação de texto. --}}
                        @if ($p->website_html)
                            {!! \App\Support\Html::clean($p->website_html) !!}
                        @else
                            {!! nl2br(e($p->description)) !!}
                        @endif
                    </div>
                @endif

                <div class="mt-6 flex flex-wrap gap-3 print:hidden">
                    <button type="button" x-cloak x-data @click="$store.favorites.toggle(@js($p->slug))" class="btn-secondary py-2 text-xs"
                            :aria-pressed="$store.favorites.has(@js($p->slug))">
                        <span x-text="$store.favorites.has(@js($p->slug)) ? @js(__('ui.property.favorite_remove')) : @js(__('ui.property.favorite_add'))"></span>
                    </button>
                    {{--
                        Partilhar: no telemóvel abre a partilha do sistema (WhatsApp,
                        mensagens…); no computador copia a ligação e confirma. Só aparece
                        com JavaScript — sem ele não haveria nada que fazer.
                    --}}
                    <button type="button" x-cloak x-data="{ copiado: false }" class="btn-secondary py-2 text-xs"
                            @click="
                                const dados = { title: @js($title), text: @js($metaDescription), url: window.location.href };
                                if (navigator.share) { try { await navigator.share(dados); } catch {} return; }
                                try { await navigator.clipboard.writeText(dados.url); copiado = true; setTimeout(() => copiado = false, 2500); } catch {}
                            ">
                        <span x-text="copiado ? @js(__('ui.property.share_copied')) : @js(__('ui.property.share'))"></span>
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

                @if ($p->features)
                    <section class="mt-12">
                        <h2 class="text-2xl font-semibold tracking-tight">{{ __('ui.property.characteristics') }}</h2>
                        <ul class="mt-5 grid grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-2 md:grid-cols-3">
                            @foreach ($p->features as $feature)
                                <li class="flex items-start gap-3">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-olive-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4 12.5 5 5L20 6.5"/></svg>
                                    <span class="capitalize">{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{--
                    Mapa: só se gmap_visible (compromisso contratual). Aparece logo ao abrir
                    a ficha: o Leaflet vem do nosso storage e só os quadrados do mapa vêm
                    do openstreetmap.org. Rodapé reduzido à linha que a licença exige.
                --}}
                <section id="mapa" class="mt-12 scroll-mt-8 print:hidden">
                    <h2 class="text-2xl font-semibold tracking-tight">{{ __('ui.property.map') }}</h2>
                    @if ($coords)
                        @php $lat = (float) $coords['lat']; $lon = (float) $coords['lon']; @endphp
                        <div x-data="{
                                map: null,
                                async init() {
                                    await this.loadLeaflet();
                                    this.draw();
                                },
                                loadLeaflet() {
                                    return new Promise((resolve) => {
                                        if (window.L) return resolve();
                                        const css = document.createElement('link'); css.rel = 'stylesheet'; css.href = @js(asset('vendor/leaflet/leaflet.css')); document.head.appendChild(css);
                                        const js = document.createElement('script'); js.src = @js(asset('vendor/leaflet/leaflet.js')); js.onload = resolve; document.head.appendChild(js);
                                    });
                                },
                                draw() {
                                    if (this.map || ! this.$refs.map) return;
                                    const pos = [{{ $lat }}, {{ $lon }}];
                                    this.map = L.map(this.$refs.map, { scrollWheelZoom: false, attributionControl: false }).setView(pos, 15);
                                    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(this.map);
                                    // Sem aspas dentro do texto: este bloco vive num atributo HTML.
                                    L.control.attribution({ prefix: false })
                                        .addAttribution('&copy; <a href=' + @js('https://www.openstreetmap.org/copyright') + ' target=_blank rel=noopener>OpenStreetMap</a>')
                                        .addTo(this.map);
                                    L.marker(pos, { icon: L.icon({
                                        iconUrl: @js(asset('vendor/leaflet/images/marker-icon.png')),
                                        iconRetinaUrl: @js(asset('vendor/leaflet/images/marker-icon-2x.png')),
                                        shadowUrl: @js(asset('vendor/leaflet/images/marker-shadow.png')),
                                        iconSize: [25, 41], iconAnchor: [12, 41], shadowSize: [41, 41],
                                    }) }).addTo(this.map);
                                },
                            }"
                            class="mt-5 overflow-hidden rounded-xl border border-sand-200 bg-sand-100">
                            <div x-ref="map" class="h-96 w-full" role="img" aria-label="{{ __('ui.property.map') }}" data-map></div>
                            <noscript><p class="p-4 text-sm"><a class="link" href="https://www.openstreetmap.org/?mlat={{ $lat }}&mlon={{ $lon }}#map=16/{{ $lat }}/{{ $lon }}" rel="noopener" target="_blank">OpenStreetMap</a></p></noscript>
                        </div>
                    @else
                        <p class="mt-4 text-sm text-ink-muted">{{ __('ui.property.map_hidden') }}</p>
                    @endif
                </section>
            </div>

            {{-- Cartão de dados, como na referência: rótulo a negrito e valor ao lado. --}}
            <div class="order-1 lg:order-none lg:col-start-2 lg:row-start-1">
                @if ($ficha || $p->energy_rating)
                    <dl class="rounded-xl border border-sand-200 bg-white px-8 py-8 text-[15px] leading-6 sm:px-10" data-testid="ficha">
                        @foreach ($ficha as $label => $value)
                            <div class="flex items-baseline gap-2.5 py-2">
                                <dt class="font-semibold">{{ $label }}:</dt>
                                <dd class="text-ink/90">{{ $value }}</dd>
                            </div>
                        @endforeach
                        @if ($p->energy_rating)
                            <div class="flex items-center gap-2.5 py-1.5">
                                <dt class="font-semibold">{{ __('ui.property.energy_certificate') }}:</dt>
                                <dd><x-property.energy-badge :rating="$p->energy_rating" /></dd>
                            </div>
                        @endif
                        @if (filled($ami))
                            <div class="pt-2 text-xs text-ink">{{ __('ui.property.ami') }}: {{ $ami }}</div>
                        @endif
                    </dl>
                @endif
            </div>

            <aside class="order-3 lg:order-none lg:col-start-2 lg:row-start-2 lg:sticky lg:top-8 lg:self-start print:hidden">
                    @if ($p->broker && ($p->broker['name'] ?? null))
                        <div class="mb-4 flex items-center gap-4 rounded-xl border border-sand-200 bg-sand-100 px-5 py-4">
                            @if ($p->broker['photo'] ?? null)
                                <img src="{{ $p->broker['photo'] }}" alt="" width="56" height="56" class="h-14 w-14 rounded-full object-cover" loading="lazy" onerror="this.style.display='none'">
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
            <section class="mt-24 print:hidden">
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

        <x-recently-viewed :exclude="$p->slug" class="mt-24 print:hidden" />
    </article>
</x-layouts.app>
