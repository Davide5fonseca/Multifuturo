{{--
    Pesquisa rápida: escolha da finalidade + texto livre. Faz GET para /comprar ou
    /arrendar com ?q= — os filtros vivem sempre na query string (partilháveis).
    Sem JS funciona na mesma; o Alpine só troca o destino do formulário.
--}}
@props(['businessType' => 'sale'])
<form {{ $attributes->merge(['class' => '']) }}
      x-data="{ type: @js($businessType), routes: { sale: @js(route('buy')), rent: @js(route('rent')) } }"
      :action="routes[type]" action="{{ $businessType === 'rent' ? route('rent') : route('buy') }}" method="get" role="search">
    <fieldset class="flex flex-wrap items-stretch gap-0 overflow-hidden rounded-lg border border-sand-200 bg-white">
        <legend class="sr-only">{{ __('ui.search.title') }}</legend>

        <label class="sr-only" for="pesquisa-tipo">{{ __('ui.search.business_type') }}</label>
        <select id="pesquisa-tipo" x-model="type" class="select-chevron border-r border-sand-200 bg-sand-100 py-3.5 pl-4 text-sm text-ink focus:outline-none">
            <option value="sale">{{ __('ui.search.buy') }}</option>
            <option value="rent">{{ __('ui.search.rent') }}</option>
        </select>

        <label class="sr-only" for="pesquisa-q">{{ __('ui.search.title') }}</label>
        <input id="pesquisa-q" name="q" type="search" value="{{ request('q') }}" placeholder="{{ __('ui.search.placeholder') }}"
               class="min-w-0 flex-1 bg-white px-5 py-3.5 text-ink placeholder:text-ink-muted focus:outline-none" autocomplete="off">

        <button type="submit" class="bg-olive-600 px-8 py-3.5 text-sm font-medium tracking-wide text-sand-50 transition-colors hover:bg-olive-700">
            {{ __('ui.search.submit') }}
        </button>
    </fieldset>
</form>
