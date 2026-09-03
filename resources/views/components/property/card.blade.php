{{--
    Cartão de imóvel — o componente mais reutilizado do site (listagens,
    destaques, semelhantes, favoritos, comparador). Fotografia a dominar, sem
    moldura nem sombra: por baixo, a localização em caixa alta e uma linha com
    tipologia, área e preço. "Exclusivo" é a única etiqueta sobre a imagem.

    Favorito e comparar vivem no localStorage e só existem com JavaScript.
--}}
@props(['property', 'eager' => false])
@php
    use App\Support\Format;
    $p = $property;
    $url = route('property.show', $p);
    $title = $p->title ?: trim(($p->property_type ?? '').' '.(Format::typology($p->bedrooms) ?? ''));
    // Cabeçalho do cartão: "Porto — Ramalde". Sem concelho, fica o título.
    $local = collect([$p->city, $p->locality])->filter()->implode(' — ') ?: $title;
    $specs = array_filter([
        $p->bedrooms !== null ? trans_choice('ui.property.rooms_count', $p->bedrooms, ['count' => $p->bedrooms]) : null,
        Format::area($p->house_area ?? $p->gross_area),
        Format::price($p->price, $p->currency, $p->business_type, $p->price_visible),
    ]);
@endphp
<article {{ $attributes->merge(['class' => 'group relative flex flex-col']) }} data-slug="{{ $p->slug }}">
    <a href="{{ $url }}" class="relative block overflow-hidden bg-sand-100" tabindex="-1" aria-hidden="true">
        <x-property.image :src="$p->cover_photo['url'] ?? null" :alt="$title" ratio="4/5" :eager="$eager"
                          class="transition-transform duration-700 group-hover:scale-[1.03]" />
        @if ($p->is_exclusive)
            <span class="absolute left-4 top-4 border border-sand-50/70 px-3 py-1.5 text-[0.65rem] font-medium uppercase tracking-label text-sand-50">{{ __('ui.property.exclusive') }}</span>
        @endif
    </a>

    {{-- Favorito e comparar: discretos, no canto oposto à etiqueta. --}}
    <div class="absolute right-1.5 top-1.5 z-10 flex flex-col gap-1 sm:right-3 sm:top-3">
        <button type="button" x-cloak x-data
                @click.prevent="$store.favorites.toggle(@js($p->slug))"
                :aria-pressed="$store.favorites.has(@js($p->slug))"
                :aria-label="$store.favorites.has(@js($p->slug)) ? @js(__('ui.property.favorite_remove')) : @js(__('ui.property.favorite_add'))"
                class="grid h-11 w-11 place-items-center text-ink hover:text-olive-700">
            {{-- 44px de área de toque; o círculo bege é o filho, para o botão não parecer enorme --}}
            <span class="grid h-9 w-9 place-items-center rounded-full bg-sand-50/90">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"
                     :fill="$store.favorites.has(@js($p->slug)) ? 'currentColor' : 'none'">
                    <path stroke-linejoin="round" d="M12 20.5s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 8a4.3 4.3 0 0 1 7.5 2.5c0 5.4-7.5 10-7.5 10Z"/>
                </svg>
            </span>
        </button>

        <button type="button" x-cloak x-data
                @click.prevent="$store.compare.toggle(@js($p->slug))"
                :aria-pressed="$store.compare.has(@js($p->slug))"
                :aria-label="$store.compare.has(@js($p->slug)) ? @js(__('ui.compare.remove')) : @js(__('ui.compare.add'))"
                class="grid h-11 w-11 place-items-center text-ink hover:text-olive-700">
            <span class="grid h-9 w-9 place-items-center rounded-full"
                  :class="$store.compare.has(@js($p->slug)) ? 'bg-olive-600 text-sand-50' : 'bg-sand-50/90'">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H5v14h4zM19 5h-4v9h4z"/>
                </svg>
            </span>
        </button>
    </div>

    <div class="mt-5">
        <h3 class="font-sans text-sm font-semibold uppercase tracking-label">
            <a href="{{ $url }}" class="after:absolute after:inset-0 after:content-[''] focus:outline-none focus-visible:underline">{{ $local }}</a>
            {{-- O título completo fica para quem ouve a página e para os motores de busca. --}}
            <span class="sr-only"> — {{ $title }}</span>
        </h3>
        @if ($specs)
            <p class="mt-1.5 text-sm uppercase tracking-wide text-ink-muted">{{ implode(', ', $specs) }}</p>
        @endif
        {{-- A referência é o que o cliente diz ao telefone: discreta, mas presente. --}}
        <p class="mt-1 text-xs text-ink-muted/80">{{ __('ui.property.reference') }} {{ $p->reference ?? $p->internal_id }}</p>
    </div>
</article>
