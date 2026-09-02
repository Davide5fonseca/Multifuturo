{{--
    Pesquisa rápida: escolha da finalidade + texto livre, com sugestões ao
    escrever (concelhos, freguesias e imóveis — SearchSuggestController). Faz
    GET para /comprar ou /arrendar com ?q= — os filtros vivem sempre na query
    string (partilháveis). Sem JS funciona na mesma: o Alpine só troca o destino
    do formulário e acrescenta as sugestões.
--}}
@props(['businessType' => 'sale'])
<form {{ $attributes->merge(['class' => '']) }}
      x-data="{ type: @js($businessType), routes: { sale: @js(route('buy')), rent: @js(route('rent')) }, ...suggestions(@js(route('search.suggest'))) }"
      :action="routes[type]" action="{{ $businessType === 'rent' ? route('rent') : route('buy') }}" method="get" role="search"
      @click.outside="close()" @keydown.escape="close()">
    <fieldset class="relative flex flex-wrap items-stretch gap-0 rounded-lg border border-sand-200 bg-white">
        <legend class="sr-only">{{ __('ui.search.title') }}</legend>

        <label class="sr-only" for="pesquisa-tipo">{{ __('ui.search.business_type') }}</label>
        <select id="pesquisa-tipo" x-model="type" x-ref="tipo" class="select-chevron rounded-l-lg border-r border-sand-200 bg-sand-100 py-3.5 pl-4 text-sm text-ink focus:outline-none">
            <option value="sale">{{ __('ui.search.buy') }}</option>
            <option value="rent">{{ __('ui.search.rent') }}</option>
        </select>

        <label class="sr-only" for="pesquisa-q">{{ __('ui.search.title') }}</label>
        <input id="pesquisa-q" name="q" type="search" value="{{ request('q') }}" placeholder="{{ __('ui.search.placeholder') }}"
               class="min-w-0 flex-1 basis-40 bg-white px-4 py-3.5 text-sm text-ink placeholder:text-ink-muted focus:outline-none sm:px-5 sm:text-base"
               autocomplete="off" role="combobox" aria-controls="pesquisa-sugestoes" aria-autocomplete="list"
               :aria-expanded="open ? 'true' : 'false'"
               :aria-activedescendant="active >= 0 ? 'sugestao-' + active : null"
               @input="onInput($event.target.value)"
               @focus="if (items.length) open = true"
               @keydown.arrow-down.prevent="move(1)"
               @keydown.arrow-up.prevent="move(-1)"
               @keydown.enter="choose($event)">

        {{-- Em ecrãs pequenos o botão passa para uma linha própria, a toda a largura. --}}
        <button type="submit" class="w-full rounded-r-lg bg-olive-600 px-8 py-3.5 text-sm font-medium tracking-wide text-sand-50 transition-colors hover:bg-olive-700 sm:w-auto">
            {{ __('ui.search.submit') }}
        </button>

        {{-- Sugestões: agrupadas por tipo, navegáveis com as setas. --}}
        <div x-cloak x-show="open" id="pesquisa-sugestoes" role="listbox" aria-label="{{ __('ui.search.suggestions') }}"
             x-transition.opacity.duration.150ms
             class="absolute inset-x-0 top-full z-30 mt-2 max-h-80 overflow-y-auto rounded-lg border border-sand-200 bg-white py-2 text-left shadow-xl shadow-ink/10">
            <template x-if="!items.length && !loading">
                <p class="px-4 py-3 text-sm text-ink-muted">{{ __('ui.search.no_suggestions') }}</p>
            </template>
            <template x-for="(item, i) in items" :key="item.url + i">
                <div>
                    <p x-show="i === 0 || items[i - 1].group !== item.group"
                       class="label px-4 pb-1 pt-3" x-text="item.group"></p>
                    <a :href="item.url" :id="'sugestao-' + i" role="option" :aria-selected="active === i ? 'true' : 'false'"
                       class="flex items-baseline justify-between gap-3 px-4 py-2 text-sm hover:bg-sand-100"
                       :class="active === i && 'bg-sand-100'"
                       @mouseenter="active = i">
                        <span class="truncate" x-text="item.label"></span>
                        <span class="shrink-0 text-xs text-ink-muted" x-text="item.hint"></span>
                    </a>
                </div>
            </template>
        </div>
    </fieldset>
</form>
