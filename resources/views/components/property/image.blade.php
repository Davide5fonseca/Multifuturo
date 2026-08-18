{{--
    Imagem de imóvel: URL do CRM (hotlink) com placeholder local em fallback,
    lazy loading e dimensões explícitas (evita layout shift). Como não controlamos
    o CDN do CRM, não há srcset — a caixa tem aspect-ratio fixo e object-cover.
--}}
@props([
    'src' => null,
    'alt' => '',
    'ratio' => '4/3',
    'eager' => false,
    'sizes' => '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw',
])
@php
    $placeholder = asset('images/placeholder-property.jpg');
    $url = $src ?: $placeholder;
@endphp
<img
    src="{{ $url }}"
    alt="{{ $alt }}"
    width="1200" height="{{ $ratio === '16/9' ? 675 : ($ratio === '1/1' ? 1200 : 900) }}"
    loading="{{ $eager ? 'eager' : 'lazy' }}"
    decoding="async"
    @if ($eager) fetchpriority="high" @endif
    data-fallback="{{ $placeholder }}"
    sizes="{{ $sizes }}"
    {{ $attributes->merge(['class' => 'h-full w-full object-cover']) }}
    style="aspect-ratio: {{ $ratio }};"
>
