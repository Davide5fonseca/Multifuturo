{{--
    Bloco que aparece ao entrar no ecrã (resources/js/motion.js). Sem
    JavaScript — ou com "reduzir movimento" ligado — nasce visível: o estado
    inicial só existe debaixo de html.js.

    tipo:  rise (sobe), fade (só opacidade), wipe (a imagem descobre-se)
    atraso: milissegundos, para escalonar à mão quando não há grelha

    As classes de quem usa o componente entram por $attributes->class(): dois
    atributos class no mesmo elemento fariam o browser ignorar o segundo — e o
    bloco ficava sem largura nenhuma.
--}}
@props(['tipo' => 'rise', 'atraso' => null, 'as' => 'div'])
<{{ $as }} data-reveal
    {{ $attributes->class(['reveal', 'reveal--fade' => $tipo === 'fade', 'reveal--wipe' => $tipo === 'wipe']) }}
    @if ($atraso) style="transition-delay: {{ (int) $atraso }}ms" @endif>
    {{ $slot }}
</{{ $as }}>
