{{--
    Script não essencial bloqueado até consentimento.
    Uso: <x-consent-script category="analytics" src="…"> ou com código inline no slot.
    Renderiza type="text/plain" — o navegador não o executa nem faz pedidos até o
    consent.js o ativar depois do opt-in dessa categoria.
--}}
@props(['category' => 'analytics', 'src' => null])
<script type="text/plain" data-consent="{{ $category }}" @if ($src) src="{{ $src }}" @endif {{ $attributes }}>{{ $slot }}</script>
