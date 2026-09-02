{{--
    Layout base do site.

    Props:
      title        — título da página (sem o nome da agência; é acrescentado aqui)
      description  — meta description
      canonical    — URL canónico (por defeito, o URL atual sem query string)
      image        — imagem Open Graph (URL absoluto)
      robots       — diretiva robots (ex.: "noindex,follow")
    Slots:
      head         — JSON-LD, meta adicionais
      default      — conteúdo da página
--}}
@props([
    'title' => null,
    'description' => null,
    'keywords' => [],
    'canonical' => null,
    'image' => null,
    'robots' => 'index,follow',
])

@php
    $agency = config('agency.name');
    $fullTitle = $title ? "{$title} — {$agency}" : $agency;
    $canonicalUrl = $canonical ?? url()->current();
    $ogImage = $image ?? asset('images/og-default.jpg');
@endphp
<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::htmlLang() }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fullTitle }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    @if ($keywords)
        <meta name="keywords" content="{{ implode(', ', $keywords) }}">
    @endif
    <meta name="robots" content="{{ $robots }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">

    {{-- Idiomas: cada versão aponta para as outras, e x-default para a principal. --}}
    @foreach (\App\Support\Locales::alternates() as $hreflang => $alternateUrl)
        <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $alternateUrl }}">
    @endforeach
    @if (\App\Support\Locales::isMultilingual())
        <link rel="alternate" hreflang="x-default" href="{{ \App\Support\Locales::switchUrl(\App\Support\Locales::default(), withQuery: false) }}">
    @endif

    {{-- Open Graph / Twitter --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $agency }}">
    <meta property="og:locale" content="{{ str_replace('-', '_', \App\Support\Locales::htmlLang()) }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    @if ($description)
        <meta property="og:description" content="{{ $description }}">
    @endif
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="{{ asset('images/marca/favicon.png') }}" type="image/png" sizes="32x32">
    <link rel="icon" href="{{ asset('images/marca/favicon-192.png') }}" type="image/png" sizes="192x192">
    <link rel="icon" href="{{ asset('images/marca/favicon-512.png') }}" type="image/png" sizes="512x512">
    <link rel="apple-touch-icon" href="{{ asset('images/marca/favicon-180.png') }}">
    <link rel="preload" href="{{ asset('fonts/bodoni-moda-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/inter-latin.woff2') }}" as="font" type="font/woff2" crossorigin>

    {{--
        Barra do comparador: aparece quando há imóveis escolhidos e acompanha o
        visitante pelo site. Só com JavaScript (a escolha vive no localStorage).
    --}}
    {{-- O aviso de cookies manda: enquanto estiver no ecrã, a barra assenta por cima dele. --}}
    <div x-cloak x-show="$store.compare.count > 0" x-transition
         x-data="{ get offset() {
             const aviso = document.querySelector('[data-consent-banner]');
             return ($store.consent?.open && aviso) ? aviso.offsetHeight : 0;
         } }"
         :style="'bottom: ' + offset + 'px'"
         class="fixed inset-x-0 bottom-0 z-30 border-t border-sand-200 bg-sand-50/95 backdrop-blur print:hidden">
        <div class="container-site flex flex-wrap items-center justify-between gap-3 py-3">
            <p class="text-sm" aria-live="polite">
                <span x-text="$store.compare.count"></span>
                <span x-text="$store.compare.count === 1 ? @js(__('ui.compare.bar_one')) : @js(__('ui.compare.bar_many'))"></span>
                <span x-show="$store.compare.full" x-transition class="ml-2 text-clay-600">{{ __('ui.compare.limit', ['max' => \App\Http\Controllers\CompareController::MAX]) }}</span>
            </p>
            <div class="flex items-center gap-3">
                <button type="button" class="link text-sm" @click="$store.compare.clear()">{{ __('ui.compare.clear') }}</button>
                <a href="{{ route('compare') }}" class="btn-primary px-6 py-2 text-sm" :class="$store.compare.count < 2 && 'pointer-events-none opacity-50'">{{ __('ui.compare.open') }}</a>
            </div>
        </div>
    </div>

    {{-- Configuração do consentimento de cookies lida pelo consent.js (sem valores sensíveis). --}}
    <script>window.MF_CONSENT = {!! json_encode(['cookie' => config('consent.cookie'), 'days' => config('consent.days'), 'version' => config('consent.version'), 'categories' => config('consent.categories'), 'endpoint' => route('consent.store')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Livewire (traz o Alpine) em TODAS as páginas — sem isto só era injetado onde havia um componente Livewire. --}}
    @livewireStyles

    {{ $head ?? '' }}
</head>
<body class="bg-sand-50 text-ink">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:bg-olive-600 focus:px-4 focus:py-2 focus:text-sand-50">
        {{ __('ui.skip_to_content') }}
    </a>

    <x-site.header />

    <main id="conteudo" class="flex-1">
        {{ $slot }}
    </main>

    <x-site.footer />
    <x-site.consent-banner />
    @livewireScripts
</body>
</html>
