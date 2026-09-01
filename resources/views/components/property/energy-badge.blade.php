{{--
    Etiqueta do certificado energético: a "casa" com a letra e "CLASSE
    ENERGÉTICA" por baixo, nas cores da escala nacional (A+ verde-escuro … F
    vermelho). SVG inline — sem imagens, sem pedidos, imprime bem.
--}}
@props(['rating'])
@php
    $cores = [
        'A+' => '#00963F', 'A' => '#3AAA35', 'B' => '#8CC63F', 'B-' => '#C8D400',
        'C' => '#D6DE23', 'D' => '#FFED00', 'E' => '#F7A600', 'F' => '#E30613',
    ];
    $cor = $cores[$rating] ?? '#9AA0A6';
    $letra = (string) $rating;
    $tamanho = match (true) { mb_strlen($letra) === 1 => 34, mb_strlen($letra) === 2 => 28, default => 12 };
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex flex-col items-center text-center leading-none']) }} role="img" aria-label="{{ __('ui.property.energy_class', ['class' => $letra]) }}">
    <svg viewBox="0 0 64 60" class="h-9 w-9" aria-hidden="true">
        <path d="M32 2 62 26v32H2V26Z" fill="{{ $cor }}"/>
        <text x="32" y="{{ $tamanho > 20 ? 48 : 43 }}" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="800" font-size="{{ $tamanho }}" fill="#fff" stroke="rgba(0,0,0,.18)" stroke-width=".6" paint-order="stroke">{{ $letra }}</text>
    </svg>
    <span class="mt-px text-[0.45rem] font-bold uppercase tracking-wide text-ink">{{ __('ui.property.energy_class_label') }}</span>
</span>
