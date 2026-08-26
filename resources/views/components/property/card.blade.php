{{--
    Cartão de imóvel — o componente mais reutilizado do site (listagens,
    destaques, semelhantes, favoritos, zonas). Fotografia 4:3 a dominar,
    badge "Exclusivo" discreto, favorito em localStorage, referência,
    título, localização, preço em Fraunces e specs em linha.
    Separação por tom (sand-100 sobre sand-50) e espaço — sem sombras.
--}}
@props(['property', 'eager' => false])
@php
    use App\Support\Format;
    $p = $property;
    $url = route('property.show', $p);
    $title = $p->title ?: trim(($p->property_type ?? '').' '.(Format::typology($p->bedrooms) ?? ''));
    $specs = array_filter([
        Format::typology($p->bedrooms),
        Format::area($p->house_area ?? $p->gross_area),
        $p->plot_area ? __('ui.property.plot_area').' '.Format::area($p->plot_area) : null,
        $p->energy_rating ? 'CE '.$p->energy_rating : null,
    ]);
@endphp
<article {{ $attributes->merge(['class' => 'group relative flex flex-col overflow-hidden rounded-2xl bg-sand-100 ring-1 ring-sand-200/70 transition duration-300 hover:ring-sand-200']) }} data-slug="{{ $p->slug }}">
    <a href="{{ $url }}" class="relative block overflow-hidden bg-sand-200" tabindex="-1" aria-hidden="true">
        <x-property.image :src="$p->cover_photo['url'] ?? null" :alt="$title" :eager="$eager" class="transition-transform duration-700 group-hover:scale-[1.04]" />
        @if ($p->is_exclusive)
            <span class="absolute left-4 top-4 rounded-full bg-sand-50 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-label text-ink">{{ __('ui.property.exclusive') }}</span>
        @endif
    </a>

    {{-- Favorito: só JS/localStorage. Sem JS, o botão não aparece (x-cloak). --}}
    <button type="button" x-cloak x-data
            @click.prevent="$store.favorites.toggle(@js($p->slug))"
            :aria-pressed="$store.favorites.has(@js($p->slug))"
            :aria-label="$store.favorites.has(@js($p->slug)) ? @js(__('ui.property.favorite_remove')) : @js(__('ui.property.favorite_add'))"
            class="absolute right-1.5 top-1.5 z-10 grid h-11 w-11 place-items-center rounded-full text-ink hover:text-olive-700 sm:right-3 sm:top-3"
            {{-- 44px de área de toque; o círculo bege é o filho, para o botão não parecer enorme --}}>
        <span class="grid h-9 w-9 place-items-center rounded-full bg-sand-50/90">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"
                 :fill="$store.favorites.has(@js($p->slug)) ? 'currentColor' : 'none'">
                <path stroke-linejoin="round" d="M12 20.5s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 8a4.3 4.3 0 0 1 7.5 2.5c0 5.4-7.5 10-7.5 10Z"/>
            </svg>
        </span>
    </button>

    <div class="flex flex-1 flex-col p-6">
        <p class="label">{{ __('ui.property.reference') }} {{ $p->reference ?? $p->internal_id }} · {{ $p->business_type->label() }}</p>
        <h3 class="mt-2 text-xl leading-snug">
            <a href="{{ $url }}" class="after:absolute after:inset-0 after:content-[''] focus:outline-none focus-visible:underline">{{ $title }}</a>
        </h3>
        <p class="mt-1 text-sm text-ink-muted">{{ Format::location($p->locality, $p->city, $p->district) }}</p>
        <p class="price mt-auto pt-5 text-2xl">{{ Format::price($p->price, $p->currency, $p->business_type, $p->price_visible) }}</p>
        @if ($specs)
            <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-muted">
                @foreach ($specs as $spec)
                    <li>{{ $spec }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</article>
