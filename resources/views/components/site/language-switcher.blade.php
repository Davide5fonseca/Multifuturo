{{--
    Seletor de idioma. Só aparece quando há mais do que um idioma ligado
    (config/locales.php), por isso num site só em português não existe.

    Cada opção aponta para a MESMA página no outro idioma — mantém a rota, o
    imóvel e os filtros da query string.
--}}
@props(['compact' => false])

@if (\App\Support\Locales::isMultilingual())
    <div {{ $attributes->merge(['class' => 'flex items-center gap-1']) }}>
        @foreach (\App\Support\Locales::enabled() as $locale)
            @php $atual = $locale === app()->getLocale(); @endphp
            <a
                href="{{ \App\Support\Locales::switchUrl($locale) }}"
                hreflang="{{ \App\Support\Locales::htmlLang($locale) }}"
                @if ($atual) aria-current="true" @endif
                title="{{ \App\Support\Locales::label($locale) }}"
                @class([
                    'grid min-h-11 min-w-11 place-items-center rounded text-xs font-medium uppercase tracking-wide transition sm:min-h-0 sm:min-w-0 sm:px-1.5 sm:py-1',
                    'bg-olive-600 text-sand-50' => $atual,
                    'text-ink/60 hover:text-olive-700' => ! $atual,
                    'text-base' => $compact,
                ])
            >
                {{ \App\Support\Locales::short($locale) }}
                <span class="sr-only">{{ \App\Support\Locales::label($locale) }}</span>
            </a>
        @endforeach
    </div>
@endif
