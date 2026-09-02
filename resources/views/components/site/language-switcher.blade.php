{{--
    Seletor de idioma. Só aparece quando há mais do que um idioma ligado
    (config/locales.php), por isso num site só em português não existe.

    Cada opção aponta para a MESMA página no outro idioma — mantém a rota, o
    imóvel e os filtros da query string.

    Desenho: um par de opções dentro de uma pílula com um traço fino à volta,
    para se ler como um interruptor e não como dois links soltos.
--}}
@props(['compact' => false])

@if (\App\Support\Locales::isMultilingual())
    <div {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full border border-sand-200 p-0.5']) }}
         role="group" aria-label="{{ __('ui.nav.language') }}">
        @foreach (\App\Support\Locales::enabled() as $locale)
            @php $atual = $locale === app()->getLocale(); @endphp
            <a
                href="{{ \App\Support\Locales::switchUrl($locale) }}"
                hreflang="{{ \App\Support\Locales::htmlLang($locale) }}"
                @if ($atual) aria-current="true" @endif
                title="{{ \App\Support\Locales::label($locale) }}"
                @class([
                    'grid place-items-center rounded-full font-medium uppercase tracking-[0.12em] transition-colors',
                    // Alvo de toque de 44 px no telemóvel; no rato basta o texto.
                    'min-h-11 min-w-11 text-xs sm:min-h-8 sm:min-w-0 sm:px-3' => ! $compact,
                    'min-h-11 min-w-11 px-4 text-sm' => $compact,
                    'bg-ink text-sand-50' => $atual,
                    'text-ink-muted hover:text-ink' => ! $atual,
                ])
            >
                {{ \App\Support\Locales::short($locale) }}
                <span class="sr-only">{{ \App\Support\Locales::label($locale) }}</span>
            </a>
        @endforeach
    </div>
@endif
