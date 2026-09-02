{{--
    Cabeçalho editorial: marca encostada à esquerda, navegação a seguir e as
    ações à direita (favoritos, idioma e um botão de contacto). Fica colado ao
    topo e ganha uma linha discreta assim que a página desce. Menu móvel em Alpine.
--}}
@php
    $links = [
        ['route' => 'buy', 'label' => __('ui.nav.buy')],
        ['route' => 'rent', 'label' => __('ui.nav.rent')],
        ['route' => 'zones.index', 'label' => __('ui.nav.zones')],
        ['route' => 'valuation', 'label' => __('ui.nav.valuation')],
        ['route' => 'about', 'label' => __('ui.nav.about')],
    ];
@endphp
<header class="sticky top-0 z-40 bg-sand-50/95 backdrop-blur print:hidden"
        x-data="{ open: false, descido: false }"
        @keydown.escape.window="open = false"
        @scroll.window="descido = window.scrollY > 24"
        :class="descido ? 'border-b border-sand-200' : 'border-b border-transparent'">
    <div class="container-site flex h-20 items-center gap-6">
        {{-- A marca, o mais à esquerda possível --}}
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3 text-ink" aria-label="{{ config('agency.name') }}">
            <img src="{{ asset('images/marca/simbolo.png') }}" alt="" width="384" height="317" class="h-8 w-auto sm:h-9">
            <span class="hidden font-serif text-base leading-tight tracking-[0.16em] uppercase sm:block">
                Multifuturo<span class="block text-[0.62em] tracking-[0.22em] text-olive-600">Propriedades</span>
            </span>
        </a>

        <nav class="ml-10 hidden items-center gap-7 whitespace-nowrap xl:flex" aria-label="{{ __('ui.nav.main') }}">
            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}"
                   @class(['text-sm tracking-wide transition-colors hover:text-olive-700', 'text-olive-700' => request()->routeIs($link['route']), 'text-ink' => ! request()->routeIs($link['route'])])
                   @if (request()->routeIs($link['route'])) aria-current="page" @endif>
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        <button type="button" class="-mr-2 order-last grid h-11 w-11 place-items-center text-ink xl:hidden" @click="open = !open" :aria-expanded="open" aria-controls="menu-movel">
            <span class="sr-only" x-text="open ? @js(__('ui.nav.menu_close')) : @js(__('ui.nav.menu_open'))"></span>
            <svg x-show="!open" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" d="M3 7h18M3 12h18M3 17h18"/></svg>
            <svg x-show="open" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
        </button>

        {{-- Direita: ações --}}
        <div class="ml-auto flex items-center gap-4">
            <a href="{{ route('favorites') }}" class="relative hidden text-ink hover:text-olive-700 sm:block" x-data>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linejoin="round" d="M12 20.5s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 8a4.3 4.3 0 0 1 7.5 2.5c0 5.4-7.5 10-7.5 10Z"/></svg>
                <span class="sr-only">{{ __('ui.nav.favorites') }}</span>
                <span x-cloak x-show="$store.favorites.count > 0" x-text="$store.favorites.count"
                      class="absolute -right-2 -top-2 min-w-4 rounded-full bg-olive-600 px-1 text-center text-[0.65rem] leading-4 text-sand-50"></span>
            </a>
            <x-site.language-switcher class="hidden lg:flex" />
            <a href="{{ route('contact') }}" class="hidden rounded-md border border-ink px-6 py-2.5 text-sm tracking-wide transition-colors hover:bg-ink hover:text-sand-50 lg:inline-block">
                {{ __('ui.nav.contact') }}
            </a>
        </div>
    </div>

    <nav id="menu-movel" x-show="open" x-cloak x-transition.opacity class="border-t border-sand-200 bg-sand-100 xl:hidden" aria-label="{{ __('ui.nav.main') }}">
        <div class="container-site flex flex-col py-4">
            @foreach ([...$links, ['route' => 'contact', 'label' => __('ui.nav.contact')]] as $link)
                <a href="{{ route($link['route']) }}"
                   @class(['border-b border-sand-200 py-3 font-serif text-xl last:border-0 hover:text-olive-700', 'text-olive-700' => request()->routeIs($link['route']), 'text-ink' => ! request()->routeIs($link['route'])])
                   @if (request()->routeIs($link['route'])) aria-current="page" @endif>
                    {{ $link['label'] }}
                </a>
            @endforeach
            <a href="{{ route('favorites') }}" class="py-3 font-serif text-xl text-ink hover:text-olive-700">{{ __('ui.nav.favorites') }}</a>
            <x-site.language-switcher class="mt-3 gap-2" compact />
        </div>
    </nav>
</header>
