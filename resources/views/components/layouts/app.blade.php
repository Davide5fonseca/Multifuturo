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

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" href="{{ asset('favicon-192.png') }}" type="image/png" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preload" href="{{ asset('fonts/fraunces-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/inter-latin.woff2') }}" as="font" type="font/woff2" crossorigin>

    {{-- Configuração do consentimento de cookies lida pelo consent.js (sem valores sensíveis). --}}
    <script>window.MF_CONSENT = {!! json_encode(['cookie' => config('consent.cookie'), 'days' => config('consent.days'), 'version' => config('consent.version'), 'categories' => config('consent.categories')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};</script>
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
