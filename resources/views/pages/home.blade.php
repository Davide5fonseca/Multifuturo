{{--
    Página inicial — linguagem editorial: fotografia grande com movimento lento,
    tipografia serifada em caixa alta, faixas de cor alternadas e composições
    assimétricas. Tudo aparece à medida que se desce (data-reveal); sem
    JavaScript, ou com "reduzir movimento", nasce visível e quieto.
--}}
@php use App\Support\Format; @endphp
<x-layouts.app :title="__('ui.home.title')" :description="__('ui.home_sections.hero_lead')" :canonical="route('home')" :image="$heroImage">
    {{-- 1. Abertura: fotografia a toda a largura, texto encostado em baixo à esquerda --}}
    <section @class(['relative isolate flex items-end overflow-hidden', 'bg-olive-900 text-sand-50' => $heroImage, 'bg-sand-100 text-ink' => ! $heroImage])
             style="min-height: min(92svh, 900px)">
        @if ($heroImage)
            <div class="parallax-frame absolute inset-0 -z-20" aria-hidden="true">
                <img src="{{ $heroImage }}" alt="" width="1920" height="1080" fetchpriority="high" decoding="async"
                     data-parallax="0.16" class="absolute inset-x-0 w-full object-cover"
                     data-fallback="{{ asset('images/placeholder-property.jpg') }}"
                     onerror="this.onerror=null;this.src=this.dataset.fallback">
            </div>
            <div class="absolute inset-0 -z-10 bg-linear-to-t from-ink/85 via-ink/60 to-ink/25" aria-hidden="true"></div>
        @endif

        <div class="container-site pb-16 pt-32 sm:pb-24">
            <x-site.reveal tipo="fade">
                <p @class(['eyebrow', 'text-sand-200' => $heroImage])>{{ __('ui.home.eyebrow') }}</p>
            </x-site.reveal>
            <x-site.reveal atraso="120">
                <h1 class="display mt-3 max-w-[16ch]">{{ __('ui.home_sections.hero_title') }}</h1>
            </x-site.reveal>
            <x-site.reveal atraso="260">
                <p @class(['mt-8 max-w-md text-base leading-relaxed', 'text-sand-100' => $heroImage, 'text-ink-muted' => ! $heroImage])>{{ __('ui.home_sections.hero_lead') }}</p>
                <a href="{{ route('buy') }}" @class(['btn mt-8', 'bg-ink text-sand-50 hover:bg-olive-900' => $heroImage, 'bg-olive-600 text-sand-50 hover:bg-olive-700' => ! $heroImage])>{{ __('ui.home_sections.hero_cta') }}</a>
            </x-site.reveal>
        </div>
    </section>

    {{-- 2. Declaração: o parágrafo grande que diz ao que vimos --}}
    <section class="container-site py-24 sm:py-32">
        <x-site.reveal>
            <p class="eyebrow">{{ __('ui.home_sections.statement_eyebrow') }}</p>
        </x-site.reveal>
        <x-site.reveal atraso="120">
            {{-- O <em> vem do ficheiro de idioma (nosso), para as palavras que interessam ficarem em itálico. --}}
            <p class="editorial mt-6 max-w-4xl">{!! __('ui.home_sections.statement') !!}</p>
        </x-site.reveal>
    </section>

    {{-- 3. Composição assimétrica: duas fotografias da carteira, desencontradas --}}
    @php $composicao = $featured->take(2)->pluck('cover_photo.url')->filter()->values(); @endphp
    @if ($composicao->count() === 2)
        <section class="container-site grid grid-cols-12 items-end gap-6 pb-24 sm:pb-32">
            <x-site.reveal tipo="wipe" class="col-span-8 lg:col-span-6">
                <div class="parallax-frame relative aspect-[4/5] sm:aspect-[3/2]">
                    <img src="{{ $composicao[0] }}" alt="" loading="lazy" decoding="async"
                         data-parallax="0.1" class="absolute inset-x-0 w-full object-cover">
                </div>
            </x-site.reveal>
            <x-site.reveal tipo="wipe" atraso="200" class="col-span-4 lg:col-start-9 lg:col-span-3 lg:-mb-24">
                <div class="parallax-frame relative aspect-square">
                    <img src="{{ $composicao[1] }}" alt="" loading="lazy" decoding="async"
                         data-parallax="0.22" class="absolute inset-x-0 w-full object-cover">
                </div>
            </x-site.reveal>
        </section>
    @endif

    {{-- 4. Destaques --}}
    @if ($featured->isNotEmpty())
        <section class="container-site pb-24 sm:pb-32">
            <div class="flex flex-wrap items-end justify-between gap-6 border-b border-sand-200 pb-6">
                <x-site.reveal>
                    <p class="eyebrow">{{ config('agency.name') }}</p>
                    <h2 class="display-sm mt-2">{{ __('ui.home_sections.featured') }}</h2>
                </x-site.reveal>
                <a href="{{ route('buy') }}" class="link text-sm">{{ __('ui.home_sections.featured_all') }}</a>
            </div>
            <div class="mt-12 grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4" data-reveal-stagger="110">
                @foreach ($featured as $i => $property)
                    <x-site.reveal>
                        <x-property.card :property="$property" :eager="$i < 3" />
                    </x-site.reveal>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 5. Faixa de cor: porquê a Multifuturo, com os números reais da carteira --}}
    <section class="band band-tan">
        <div class="container-site">
            <x-site.reveal>
                <p class="eyebrow">{{ __('ui.home_sections.why') }}</p>
                <h2 class="editorial mt-6 max-w-4xl">{!! __('ui.home_sections.why_statement') !!}</h2>
            </x-site.reveal>

            <div class="mt-20 grid gap-12 sm:grid-cols-3" data-reveal-stagger="140">
                @foreach ([1, 2, 3] as $n)
                    <x-site.reveal>
                        <span class="flex items-center gap-3" aria-hidden="true">
                            <span class="font-serif text-sm italic text-olive-700">0{{ $n }}</span>
                            <span class="h-px w-12 bg-olive-600/50"></span>
                        </span>
                        <h3 class="mt-5 font-serif text-xl">{{ __("ui.home_sections.why_{$n}_title") }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-ink-muted">{{ __("ui.home_sections.why_{$n}_text") }}</p>
                    </x-site.reveal>
                @endforeach
            </div>

            {{-- Números que contam ao entrar no ecrã. São a carteira real, não promessas. --}}
            <div class="mt-20 grid gap-10 border-t border-ink/10 pt-12 sm:grid-cols-3" data-reveal-stagger="120">
                @foreach ($stats as $chave => $valor)
                    <x-site.reveal class="text-center">
                        <p class="stat"><span data-count="{{ $valor }}">0</span></p>
                        <p class="mt-2 text-sm text-ink-muted">{{ trans_choice("ui.home_sections.stat_{$chave}", $valor) }}</p>
                    </x-site.reveal>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 6. Zonas --}}
    @if ($cities->isNotEmpty())
        <section class="container-site py-24 sm:py-32">
            <div class="flex flex-wrap items-end justify-between gap-6 border-b border-sand-200 pb-6">
                <x-site.reveal>
                    <h2 class="display-sm">{{ __('ui.home_sections.zones') }}</h2>
                </x-site.reveal>
                <a href="{{ route('zones.index') }}" class="link text-sm">{{ __('ui.home_sections.zones_all') }}</a>
            </div>
            <ul class="mt-4" data-reveal-stagger="70">
                @foreach ($cities->take(8) as $c)
                    <x-site.reveal as="li" class="border-b border-sand-200">
                        <a href="{{ route('zones.city', $c['slug']) }}" class="group flex items-baseline justify-between gap-6 py-5 transition-colors hover:text-olive-700">
                            <span class="font-serif text-2xl uppercase tracking-tight sm:text-4xl">{{ $c['name'] }}</span>
                            <span class="flex items-center gap-4 text-xs text-ink-muted">
                                {{ $c['count'] }}
                                <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12h16m0 0-6-6m6 6-6 6"/></svg>
                            </span>
                        </a>
                    </x-site.reveal>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- 7. Vistos recentemente (só a quem já visitou fichas) --}}
    <x-recently-viewed class="container-site pb-24 sm:pb-32" />

    {{-- 8. Faixa escura: avaliação e contacto --}}
    <section class="band band-dark">
        <div class="container-site text-center">
            <x-site.reveal>
                <h2 class="display-sm mx-auto max-w-3xl">{{ __('ui.home_sections.cta_title') }}</h2>
                <p class="mx-auto mt-6 max-w-md text-sand-200">{{ __('ui.home_sections.cta_lead') }}</p>
                <div class="mt-10 flex flex-wrap justify-center gap-4">
                    <a href="{{ route('valuation') }}" class="btn bg-sand-50 text-ink hover:bg-sand-100">{{ __('ui.home_sections.cta_button') }}</a>
                    <a href="{{ route('contact') }}" class="btn border border-sand-200/40 text-sand-50 hover:bg-sand-50/10">{{ __('ui.home_sections.cta_contact') }}</a>
                </div>
            </x-site.reveal>
        </div>
    </section>
</x-layouts.app>
