{{--
    Homepage: hero full-bleed com texto centrado → pesquisa rápida → imóveis em
    destaque → sobre a agência → porquê (3 colunas) → zonas → banda de contacto.
--}}
@php use App\Support\Format; @endphp
<x-layouts.app :title="__('ui.home.title')" :description="__('ui.home_sections.hero_lead')" :canonical="route('home')" :image="$heroImage">
    {{-- 1. Hero: fotografia grande a dominar; texto centrado sobre véu escuro suave --}}
    {{-- Sem fotografia, o hero é bege com texto escuro (nunca um bloco grande de azeitona). --}}
    <section @class(['relative isolate flex min-h-[520px] items-center justify-center overflow-hidden h-[min(80svh,760px)] sm:h-[min(85svh,820px)]', 'bg-olive-900 text-sand-50' => $heroImage, 'bg-sand-100 text-ink' => ! $heroImage])>
        @if ($heroImage)
            <img src="{{ $heroImage }}" alt="" width="1920" height="1080" fetchpriority="high" decoding="async"
                 class="absolute inset-0 -z-20 h-full w-full object-cover" data-fallback="{{ asset('images/placeholder-property.jpg') }}"
                 onerror="this.onerror=null;this.src=this.dataset.fallback">
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-ink/75 via-ink/55 to-ink/30" aria-hidden="true"></div>
        @endif
        <div class="container-site py-24 text-center">
            <p @class(['label', 'text-sand-200!' => $heroImage])>{{ __('ui.home.eyebrow') }}</p>
            <h1 class="mx-auto mt-5 max-w-3xl text-4xl leading-tight sm:text-6xl">{{ __('ui.home_sections.hero_title') }}</h1>
            <p @class(['mx-auto mt-6 max-w-xl text-lg', 'text-sand-100' => $heroImage, 'text-ink-muted' => ! $heroImage])>{{ __('ui.home_sections.hero_lead') }}</p>
            <div class="mt-10 flex justify-center">
                <a href="{{ route('buy') }}" @class(['btn', 'bg-sand-50 text-ink hover:bg-sand-100' => $heroImage, 'bg-olive-600 text-sand-50 hover:bg-olive-700' => ! $heroImage])>{{ __('ui.home_sections.hero_cta') }}</a>
            </div>
        </div>
    </section>

    {{-- 2. Pesquisa rápida --}}
    <section class="container-site relative z-10 -mt-12">
        <x-site.search-form class="mx-auto max-w-3xl rounded-2xl bg-sand-50 p-2.5 shadow-xl shadow-ink/10 ring-1 ring-sand-200" />
    </section>

    {{-- 3. Destaques --}}
    @if ($featured->isNotEmpty())
        <section class="container-site pt-24">
            <div class="flex items-end justify-between gap-6">
                <div>
                    <p class="label">{{ config('agency.name') }}</p>
                    <h2 class="mt-3 text-3xl sm:text-4xl">{{ __('ui.home_sections.featured') }}</h2>
                </div>
                <a href="{{ route('buy') }}" class="link hidden text-sm sm:inline">{{ __('ui.home_sections.featured_all') }}</a>
            </div>
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featured as $i => $property)
                    <x-property.card :property="$property" :eager="$i < 3" />
                @endforeach
            </div>
            <a href="{{ route('buy') }}" class="link mt-8 inline-block text-sm sm:hidden">{{ __('ui.home_sections.featured_all') }}</a>
        </section>
    @endif

    {{-- 3b. Vistos recentemente (só aparece a quem já visitou fichas) --}}
    <x-recently-viewed class="container-site pt-24" />

    {{-- 4. Sobre --}}
    <section class="container-site grid gap-10 pt-24 lg:grid-cols-[1fr_2fr]">
        <div>
            <p class="label">{{ __('ui.nav.about') }}</p>
            <h2 class="mt-3 text-3xl sm:text-4xl">{{ __('ui.home_sections.about') }}</h2>
        </div>
        <div class="max-w-2xl space-y-5 text-lg text-ink/90">
            <p>{{ __('ui.home_sections.about_text_1') }}</p>
            <p>{{ __('ui.home_sections.about_text_2') }}</p>
            <a href="{{ route('about') }}" class="link inline-block text-base">{{ __('ui.home_sections.about_cta') }}</a>
        </div>
    </section>

    {{-- 5. Porquê — três colunas, fundo alternado --}}
    <section class="mt-24 bg-sand-100">
        <div class="container-site py-20 text-center">
            <h2 class="text-3xl sm:text-4xl">{{ __('ui.home_sections.why') }}</h2>
            <p class="mx-auto mt-4 max-w-xl text-ink-muted">{{ __('ui.home_sections.why_lead') }}</p>
            <div class="mt-14 grid gap-12 text-left sm:grid-cols-3">
                @foreach ([1, 2, 3] as $n)
                    <div>
                        <span class="flex items-center gap-3" aria-hidden="true"><span class="font-serif text-sm text-olive-700">0{{ $n }}</span><span class="h-px w-10 bg-olive-600"></span></span>
                        <h3 class="mt-5 font-sans text-lg font-medium">{{ __("ui.home_sections.why_{$n}_title") }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-ink-muted">{{ __("ui.home_sections.why_{$n}_text") }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 6. Zonas --}}
    @if ($cities->isNotEmpty())
        <section class="container-site pt-24">
            <div class="flex items-end justify-between gap-6">
                <h2 class="text-3xl sm:text-4xl">{{ __('ui.home_sections.zones') }}</h2>
                <a href="{{ route('zones.index') }}" class="link text-sm">{{ __('ui.home_sections.zones_all') }}</a>
            </div>
            <ul class="mt-10 grid gap-px overflow-hidden rounded-xl border border-sand-200 bg-sand-200 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($cities->take(8) as $c)
                    <li class="bg-sand-50">
                        <a href="{{ route('zones.city', $c['slug']) }}" class="group flex items-baseline justify-between gap-4 px-6 py-6 hover:bg-sand-100">
                            <span class="font-serif text-2xl group-hover:text-olive-700">{{ $c['name'] }}</span>
                            <span class="text-xs text-ink-muted">{{ $c['count'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- 7. Banda de contacto / avaliação --}}
    <section class="container-site pt-24">
        {{-- Sem áreas grandes de azeitona: a banda é bege escuro; o verde fica nos botões. --}}
        <div class="rounded-2xl border border-sand-200 bg-sand-100 px-8 py-16 text-center sm:px-16">
            <h2 class="text-3xl sm:text-4xl">{{ __('ui.home_sections.cta_title') }}</h2>
            <p class="mx-auto mt-4 max-w-md text-ink-muted">{{ __('ui.home_sections.cta_lead') }}</p>
            <div class="mt-8 flex flex-wrap justify-center gap-4">
                <a href="{{ route('valuation') }}" class="btn-primary">{{ __('ui.home_sections.cta_button') }}</a>
                <a href="{{ route('contact') }}" class="btn-secondary">{{ __('ui.home_sections.cta_contact') }}</a>
            </div>
        </div>
    </section>
</x-layouts.app>
