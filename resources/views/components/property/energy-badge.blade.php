{{--
    Etiqueta da classe energética: a "casa" com a letra branca, nas cores da
    escala (A+ verde-escuro … F vermelho), como as etiquetas dos certificados.
    SVG inline — sem imagens, sem pedidos, imprime bem.
--}}
@props(['rating'])
@php
    $cores = [
        'A+' => '#1B7A3D', 'A' => '#2F9E45', 'B' => '#5FB65B', 'B-' => '#8FCA8B',
        'C' => '#F8B26A', 'D' => '#F7E463', 'E' => '#F58220', 'F' => '#EF3E36',
    ];
    $letra = (string) $rating;
    $cor = $cores[$letra] ?? '#9AA0A6';
    // "A+" e "B-": a letra grande e o sinal pequeno, levantado.
    $base = preg_replace('/[+\-–]$/u', '', $letra);
    $sinal = $base === $letra ? '' : mb_substr($letra, -1);
    $sinal = $sinal === '-' ? '−' : $sinal;
    $curto = mb_strlen($base) <= 1;
@endphp
<span {{ $attributes->merge(['class' => 'inline-block leading-none']) }} role="img" aria-label="{{ __('ui.property.energy_class', ['class' => $letra]) }}">
    <svg viewBox="0 0 64 62" class="h-10 w-10" aria-hidden="true">
        <path d="M30.6 3.1a2 2 0 0 1 2.8 0l27 24.4c.4.4.6.9.6 1.5V58a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V29c0-.6.2-1.1.6-1.5Z" fill="{{ $cor }}"/>
        @if ($curto)
            <text x="{{ $sinal !== '' ? 28 : 32 }}" y="52" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="700" font-size="34" fill="#fff">{{ $base }}@if ($sinal !== '')<tspan dx="1" dy="-14" font-size="18">{{ $sinal }}</tspan>@endif</text>
        @else
            <text x="32" y="48" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="700" font-size="12" fill="#fff">{{ $letra }}</text>
        @endif
    </svg>
</span>
