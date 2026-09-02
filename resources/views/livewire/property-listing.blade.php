@php
    use App\Support\Format;
    $opts = $this->options;
    $results = $this->properties;
@endphp
<div class="container-site pb-24" x-data="{ filtersOpen: false }">
    {{-- Cabeçalho da secção + barra de pesquisa: título curto, resultado, ordenação --}}
    <div class="flex flex-col gap-6 border-b border-sand-200 pb-8 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="label">{{ $businessTypeEnum === \App\Enums\BusinessType::Sale ? __('ui.listing.buy_eyebrow') : __('ui.listing.rent_eyebrow') }}</p>
            <h1 class="mt-3 text-4xl sm:text-5xl">{{ $businessTypeEnum === \App\Enums\BusinessType::Sale ? __('ui.listing.buy_title') : __('ui.listing.rent_title') }}</h1>
            <p class="mt-3 text-sm text-ink-muted" aria-live="polite">{{ trans_choice('ui.listing.results', $results->total(), ['count' => number_format($results->total(), 0, ',', ' ')]) }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <label class="sr-only" for="lst-sort">{{ __('ui.listing.sort') }}</label>
            <select id="lst-sort" wire:model.live="sort" class="field w-auto py-2 text-sm">
                <option value="recent">{{ __('ui.listing.sort_recent') }}</option>
                <option value="price_asc">{{ __('ui.listing.sort_price_asc') }}</option>
                <option value="price_desc">{{ __('ui.listing.sort_price_desc') }}</option>
            </select>
            <button type="button" class="btn-secondary py-2 lg:hidden" @click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen" aria-controls="lst-filters">
                {{ __('ui.listing.filters') }}
            </button>
        </div>
    </div>

    <div class="mt-8 grid gap-10 lg:grid-cols-[280px_minmax(0,1fr)]">
        {{-- Filtros: coluna lateral em desktop, painel colapsável em mobile. Funciona sem JS (form GET). --}}
        <aside id="lst-filters" class="lg:sticky lg:top-8 lg:self-start" :class="filtersOpen ? '' : 'max-lg:hidden'">
            <form method="get" action="{{ url()->current() }}" wire:submit.prevent class="grid gap-6">
                <div>
                    <label for="lst-q" class="label">{{ __('ui.listing.search') }}</label>
                    <input id="lst-q" name="q" type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('ui.listing.search_placeholder') }}" class="field mt-2 py-2 text-sm" autocomplete="off">
                </div>

                <div>
                    <label for="lst-type" class="label">{{ __('ui.listing.type') }}</label>
                    <select id="lst-type" name="tipo" wire:model.live="type" class="field mt-2 py-2 text-sm">
                        <option value="">{{ __('ui.listing.any_type') }}</option>
                        @foreach ($opts['types'] as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="lst-bedrooms" class="label">{{ __('ui.listing.bedrooms') }}</label>
                    <select id="lst-bedrooms" name="tipologia" wire:model.live="bedrooms" class="field mt-2 py-2 text-sm">
                        <option value="">{{ __('ui.listing.any_bedrooms') }}</option>
                        @foreach ($opts['bedrooms'] as $b)
                            <option value="{{ $b }}">{{ __('ui.listing.bedrooms_min', ['n' => $b]) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid gap-3">
                    <div>
                        <label for="lst-city" class="label">{{ __('ui.listing.city') }}</label>
                        <select id="lst-city" name="concelho" wire:model.live="city" class="field mt-2 py-2 text-sm">
                            <option value="">{{ __('ui.listing.any_city') }}</option>
                            @foreach ($opts['cities'] as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="lst-locality" class="label">{{ __('ui.listing.locality') }}</label>
                        <select id="lst-locality" name="freguesia" wire:model.live="locality" class="field mt-2 py-2 text-sm" @disabled($city === '')>
                            <option value="">{{ __('ui.listing.any_locality') }}</option>
                            @foreach ($opts['localities'] as $l)
                                <option value="{{ $l }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="lst-pmin" class="label">{{ __('ui.listing.price_min') }}</label>
                        <input id="lst-pmin" name="preco_min" type="text" inputmode="numeric" wire:model.live.debounce.600ms="priceMin" class="field mt-2 py-2 text-sm" placeholder="€">
                    </div>
                    <div>
                        <label for="lst-pmax" class="label">{{ __('ui.listing.price_max') }}</label>
                        <input id="lst-pmax" name="preco_max" type="text" inputmode="numeric" wire:model.live.debounce.600ms="priceMax" class="field mt-2 py-2 text-sm" placeholder="€">
                    </div>
                </div>

                <div>
                    <label for="lst-amin" class="label">{{ __('ui.listing.area_min') }}</label>
                    <input id="lst-amin" name="area_min" type="text" inputmode="numeric" wire:model.live.debounce.600ms="areaMin" class="field mt-2 py-2 text-sm">
                </div>

                @if ($opts['features'])
                    {{--
                        Características fechadas num campo, como o "Tipo de imóvel": o botão
                        mostra o resumo e abre a lista de opções (várias à escolha). Sem
                        JavaScript, o noscript mostra a lista aberta e o botão fica escondido.
                    --}}
                    <div x-data="{ open: false }" @keydown.escape.window="open = false" class="relative" wire:key="filtro-caracteristicas">
                        <span class="label" id="lst-features-label">{{ __('ui.listing.features') }}</span>
                        <button type="button" x-cloak @click="open = ! open" :aria-expanded="open" aria-haspopup="true" aria-labelledby="lst-features-label"
                                class="field mt-2 flex items-center justify-between gap-2 py-2 text-left text-sm">
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

                <div class="flex items-center gap-4">
                    <noscript><button type="submit" class="btn-primary py-2">{{ __('ui.listing.apply') }}</button></noscript>
                    @if ($this->hasFilters())
                        <button type="button" wire:click="clearFilters" class="link text-sm">{{ __('ui.listing.clear_filters') }}</button>
                    @endif
                </div>
            </form>
        </aside>

        {{-- Resultados --}}
        <div wire:loading.class="opacity-60" class="transition-opacity">
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
                <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($results as $i => $property)
                        <x-property.card :property="$property" :eager="$i < 3" wire:key="p-{{ $property->id }}" />
                    @endforeach
                </div>

                <div class="mt-12">
                    {{ $results->onEachSide(1)->links('pagination.multifuturo') }}
                </div>
            @endif
        </div>
    </div>
</div>
