{{-- Cabeçalho: marca à esquerda, navegação à direita; menu móvel em Alpine (vem com o Livewire). --}}
@php
    $links = [
        ['route' => 'buy', 'label' => __('ui.nav.buy')],
        ['route' => 'rent', 'label' => __('ui.nav.rent')],
        ['route' => 'zones.index', 'label' => __('ui.nav.zones')],
        ['route' => 'valuation', 'label' => __('ui.nav.valuation')],
        ['route' => 'about', 'label' => __('ui.nav.about')],
        ['route' => 'contact', 'label' => __('ui.nav.contact')],
    ];
@endphp
<header class="border-b border-sand-200 bg-sand-50" x-data="{ open: false }" @keydown.escape.window="open = false">
    <div class="container-site flex h-20 items-center justify-between gap-8">
        <a href="{{ route('home') }}" class="font-serif text-2xl font-normal tracking-tight text-ink" aria-label="{{ config('agency.name') }}">
            Multifuturo<span class="text-olive-600">.</span>
        </a>

        <nav class="ml-auto hidden items-center gap-8 lg:flex" aria-label="{{ __('ui.nav.main') }}">
            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}"
                   @class(['text-sm tracking-wide transition-colors hover:text-olive-700', 'text-olive-700 font-medium' => request()->routeIs($link['route']), 'text-ink' => ! request()->routeIs($link['route'])])
                   @if (request()->routeIs($link['route'])) aria-current="page" @endif>
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        <a href="{{ route('favorites') }}" class="hidden items-center gap-2 text-sm text-ink hover:text-olive-700 lg:flex" x-data>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linejoin="round" d="M12 20.5s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 8a4.3 4.3 0 0 1 7.5 2.5c0 5.4-7.5 10-7.5 10Z"/></svg>
            <span class="sr-only">{{ __('ui.nav.favorites') }}</span>
            <span x-cloak x-show="$store.favorites.count > 0" x-text="$store.favorites.count" class="min-w-5 rounded-full bg-olive-600 px-1.5 text-center text-xs text-sand-50"></span>
        </a>

        <x-site.language-switcher class="ml-4 hidden lg:flex" />

        <button type="button" class="lg:hidden -mr-2 p-2 text-ink" @click="open = !open" :aria-expanded="open" aria-controls="menu-movel">
            <span class="sr-only" x-text="open ? @js(__('ui.nav.menu_close')) : @js(__('ui.nav.menu_open'))"></span>
            <svg x-show="!open" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" d="M3 7h18M3 12h18M3 17h18"/></svg>
            <svg x-show="open" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>

    <nav id="menu-movel" x-show="open" x-cloak x-transition.opacity class="border-t border-sand-200 bg-sand-100 lg:hidden" aria-label="{{ __('ui.nav.main') }}">
        <div class="container-site flex flex-col py-4">
            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}" class="py-3 text-base text-ink hover:text-olive-700 border-b border-sand-200 last:border-0">
                    {{ $link['label'] }}
                </a>
            @endforeach
            <a href="{{ route('favorites') }}" class="py-3 text-base text-ink hover:text-olive-700">{{ __('ui.nav.favorites') }}</a>
            <x-site.language-switcher class="mt-3 gap-2" compact />
        </div>
    </nav>
</header>
