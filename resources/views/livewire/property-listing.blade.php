@php
    use App\Support\Format;
    $opts = $this->options;
    $results = $this->properties;
    $venda = $businessTypeEnum === \App\Enums\BusinessType::Sale;
@endphp
{{--
    Listagem: barra de filtros horizontal por cima dos resultados, com campos de
    uma linha só. Os filtros de todos os dias ficam à vista; a pesquisa livre, a
    área e as comodidades ficam atrás de "mais filtros".

    Sem JavaScript continua a ser um formulário GET com o botão de procurar.
--}}
<div class="container-site pb-24 pt-4" x-data="{ maisFiltros: false }">
    {{--
        A página abre direta nos separadores e nos filtros (pedido do cliente).
        O título e a contagem continuam a existir para os leitores de ecrã e para
        os motores de busca — uma página sem <h1> não se anuncia a ninguém.
    --}}
    <h1 class="sr-only">{{ $venda ? __('ui.listing.buy_title') : __('ui.listing.rent_title') }}</h1>
    <p class="sr-only" aria-live="polite">{{ trans_choice('ui.listing.results', $results->total(), ['count' => number_format($results->total(), 0, ',', ' ')]) }}</p>

    <form method="get" action="{{ url()->current() }}" wire:submit.prevent id="lst-filters" class="mt-4">
        {{-- Finalidade à esquerda, ordenação e mais filtros à direita --}}
        <div class="flex flex-wrap items-center justify-between gap-x-8 gap-y-4 border-b border-sand-200 pb-4">
            <nav class="flex items-baseline gap-8" aria-label="{{ __('ui.listing.filters') }}">
                @foreach ([['buy', __('ui.nav.buy')], ['rent', __('ui.nav.rent')]] as [$rota, $rotulo])
                    <a href="{{ route($rota) }}"
                       @class([
                           'label text-sm transition-colors',
                           'border-b-2 border-ink pb-1 text-ink' => request()->routeIs($rota),
                           'text-ink-muted hover:text-ink' => ! request()->routeIs($rota),
                       ])
                       @if (request()->routeIs($rota)) aria-current="page" @endif>{{ $rotulo }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-5">
                <div class="flex items-baseline gap-3">
                    <label for="lst-sort" class="label whitespace-nowrap">{{ __('ui.listing.sort') }}</label>
                    <select id="lst-sort" name="ordenar" wire:model.live="sort" class="field-line select-chevron min-h-0 w-auto py-1 text-sm">
                        <option value="recent">{{ __('ui.listing.sort_recent') }}</option>
                        <option value="price_asc">{{ __('ui.listing.sort_price_asc') }}</option>
                        <option value="price_desc">{{ __('ui.listing.sort_price_desc') }}</option>
                    </select>
                </div>

                <button type="button" @click="maisFiltros = !maisFiltros" :aria-expanded="maisFiltros" aria-controls="lst-mais"
                        class="grid h-11 w-11 place-items-center text-ink transition-colors hover:text-olive-700">
                    <span class="sr-only">{{ __('ui.listing.more_filters') }}</span>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" d="M7 4v6m0 4v6M17 4v10m0 4v2M4 12h6m4 2h6"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Onde --}}
        <div class="mt-8 grid gap-x-8 gap-y-7 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="lst-district" class="label">{{ __('ui.listing.district') }}</label>
                <select id="lst-district" name="distrito" wire:model.live="district" class="field-line select-chevron mt-1">
                    <option value="">{{ __('ui.listing.any_district') }}</option>
                    @foreach ($opts['districts'] as $d)
                        <option value="{{ $d }}">{{ $d }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="lst-city" class="label">{{ __('ui.listing.city') }}</label>
                <select id="lst-city" name="concelho" wire:model.live="city" class="field-line select-chevron mt-1">
                    <option value="">{{ __('ui.listing.any_city') }}</option>
                    @foreach ($opts['cities'] as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="lst-locality" class="label">{{ __('ui.listing.locality') }}</label>
                <select id="lst-locality" name="freguesia" wire:model.live="locality" class="field-line select-chevron mt-1" @disabled($city === '')>
                    <option value="">{{ __('ui.listing.any_locality') }}</option>
                    @foreach ($opts['localities'] as $l)
                        <option value="{{ $l }}">{{ $l }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- O quê e quanto --}}
        <div class="mt-7 grid gap-x-8 gap-y-7 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1fr_auto] lg:items-end">
            <div>
                <label for="lst-type" class="label">{{ __('ui.listing.type') }}</label>
                <select id="lst-type" name="tipo" wire:model.live="type" class="field-line select-chevron mt-1">
                    <option value="">{{ __('ui.listing.any_type') }}</option>
                    @foreach ($opts['types'] as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="lst-bedrooms" class="label">{{ __('ui.listing.bedrooms') }}</label>
                <select id="lst-bedrooms" name="tipologia" wire:model.live="bedrooms" class="field-line select-chevron mt-1">
                    <option value="">{{ __('ui.listing.any_bedrooms') }}</option>
                    @foreach ($opts['bedrooms'] as $b)
                        <option value="{{ $b }}">{{ __('ui.listing.bedrooms_min', ['n' => $b]) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="lst-pmin" class="label">{{ __('ui.listing.price_min') }}</label>
                <input id="lst-pmin" name="preco_min" type="text" inputmode="numeric" wire:model.live.debounce.600ms="priceMin" class="field-line mt-1" placeholder="€">
            </div>
            <div>
                <label for="lst-pmax" class="label">{{ __('ui.listing.price_max') }}</label>
                <input id="lst-pmax" name="preco_max" type="text" inputmode="numeric" wire:model.live.debounce.600ms="priceMax" class="field-line mt-1" placeholder="€">
            </div>
            {{-- Com JavaScript os filtros já se aplicam sozinhos; o botão é para quem não o tem. --}}
            <button type="submit" class="grid h-11 w-11 place-items-center justify-self-start text-ink transition-colors hover:text-olive-700 lg:justify-self-end">
                <span class="sr-only">{{ __('ui.search.submit') }}</span>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/>
                </svg>
            </button>
        </div>

        {{-- Mais filtros: pesquisa livre, área e comodidades --}}
        <div id="lst-mais" x-show="maisFiltros" x-cloak x-transition.opacity.duration.200ms
             class="mt-7 grid gap-x-8 gap-y-7 border-t border-sand-200 pt-7 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="lst-q" class="label">{{ __('ui.listing.search') }}</label>
                <input id="lst-q" name="q" type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('ui.listing.search_placeholder') }}" class="field-line mt-1" autocomplete="off">
            </div>
            <div>
                <label for="lst-amin" class="label">{{ __('ui.listing.area_min') }}</label>
                <input id="lst-amin" name="area_min" type="text" inputmode="numeric" wire:model.live.debounce.600ms="areaMin" class="field-line mt-1">
            </div>

            @if ($opts['features'])
                {{-- Características fechadas num campo: o botão resume, a lista abre por baixo. --}}
                <div x-data="{ open: false }" @keydown.escape.window="open = false" class="relative" wire:key="filtro-caracteristicas">
                    <span class="label" id="lst-features-label">{{ __('ui.listing.features') }}</span>
                    <button type="button" x-cloak @click="open = ! open" :aria-expanded="open" aria-haspopup="true" aria-labelledby="lst-features-label"
                            class="field-line mt-1 flex items-center justify-between gap-2 text-left">
                        <span class="truncate {{ count($features) === 1 ? 'capitalize' : '' }}">
                            @if (count($features) === 0)
                                {{ __('ui.listing.any_features') }}
                            @elseif (count($features) === 1)
                                {{ $features[0] }}
                            @else
                                {{ trans_choice('ui.listing.features_selected', count($features), ['count' => count($features)]) }}
                            @endif
                        </span>
                        <svg class="h-4 w-4 shrink-0 text-ink-muted transition-transform" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-cloak x-show="open" @click.outside="open = false" x-transition.opacity.duration.150ms
                         class="absolute z-20 mt-2 max-h-72 w-full overflow-y-auto rounded-md border border-sand-200 bg-white p-3 shadow-lg"
                         role="group" aria-labelledby="lst-features-label">
                        <div class="grid gap-2 text-sm">
                            @foreach ($opts['features'] as $f)
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="caracteristicas[]" value="{{ $f }}" wire:model.live="features" @checked(in_array($f, $features, true)) class="h-5 w-5 shrink-0 accent-olive-600">
                                    <span class="capitalize">{{ $f }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <noscript>
                        <div class="mt-3 grid gap-2 text-sm">
                            @foreach ($opts['features'] as $f)
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="caracteristicas[]" value="{{ $f }}" @checked(in_array($f, $features, true)) class="h-5 w-5 shrink-0 accent-olive-600">
                                    <span class="capitalize">{{ $f }}</span>
                                </label>
                            @endforeach
                        </div>
                    </noscript>
                </div>
            @endif
        </div>

        @if ($this->hasFilters())
            <div class="mt-6">
                <button type="button" wire:click="clearFilters" class="link text-sm">{{ __('ui.listing.clear_filters') }}</button>
            </div>
        @endif
        <noscript><button type="submit" class="btn-primary mt-6 py-2">{{ __('ui.listing.apply') }}</button></noscript>
    </form>

    {{-- Resultados --}}
    <div wire:loading.class="opacity-60" class="mt-14 transition-opacity">

        @if ($results->isEmpty())
            <div class="rounded-xl border border-sand-200 bg-sand-100 px-6 py-16 text-center">
                <p class="text-lg">{{ __('ui.listing.empty') }}</p>
                @if ($this->hasFilters())
                    <div class="mt-6 flex justify-center">
                        <button type="button" wire:click="clearFilters" class="btn-secondary">{{ __('ui.listing.clear_filters') }}</button>
                    </div>
                @endif
            </div>
        @else
            <div class="grid gap-x-8 gap-y-14 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4" data-reveal-stagger="130">
                @foreach ($results as $i => $property)
                    <x-site.reveal wire:key="p-{{ $property->id }}">
                        <x-property.card :property="$property" :eager="$i < 3" />
                    </x-site.reveal>
                @endforeach
            </div>

            {{--
                Scroll infinito: a sentinela pede o bloco seguinte quando entra no
                ecrã (e o botão faz o mesmo ao ser clicado — teclado e leitores de
                ecrã incluídos). A paginação numerada fica sempre por baixo: é o que
                funciona sem JavaScript e o que os motores de busca seguem.
            --}}
            @if ($this->hasMore())
                <div class="mt-14 flex flex-col items-center gap-3"
                     x-data="{
                         init() {
                             const io = new IntersectionObserver((entries) => {
                                 if (entries.some((e) => e.isIntersecting)) { io.disconnect(); $wire.loadMore(); }
                             }, { rootMargin: '400px' });
                             io.observe(this.$el);
                         },
                     }"
                     wire:key="mais-{{ $results->count() }}">
                    <p class="text-sm text-ink-muted" aria-live="polite">
                        {{ __('ui.listing.showing', ['shown' => number_format($results->count(), 0, ',', ' '), 'total' => number_format($results->total(), 0, ',', ' ')]) }}
                    </p>
                    <button type="button" wire:click="loadMore" wire:loading.attr="disabled" class="btn-secondary">
                        <span wire:loading.remove wire:target="loadMore">{{ __('ui.listing.load_more') }}</span>
                        <span wire:loading wire:target="loadMore">{{ __('ui.listing.loading') }}</span>
                    </button>
                </div>
            @endif

            <div class="mt-12">
                {{ $results->onEachSide(1)->links('pagination.multifuturo') }}
            </div>
        @endif
    </div>
</div>
