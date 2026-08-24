{{--
    Leaflet para o mapa da ficha do imóvel. Servido do nosso storage, não de um
    CDN; carregado só nas páginas de criar/editar imóvel (ver AdminPanelProvider).
--}}
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<script src="{{ asset('vendor/leaflet/leaflet.js') }}" defer></script>
