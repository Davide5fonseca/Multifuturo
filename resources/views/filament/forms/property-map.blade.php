{{--
    Mapa da ficha do imóvel (separador Localização), como no CRM.

    Leaflet + OpenStreetMap: sem chave de API e sem custo. O ficheiro é servido
    do nosso storage (public/vendor/leaflet), não de um CDN; só os quadrados do
    mapa é que vêm do openstreetmap.org.

    Arrastar o marcador — ou clicar no mapa — escreve a latitude e a longitude
    nos campos do formulário.
--}}
@php
    $statePath = $getStatePath();
    $latPath = 'data.lat';
    $lonPath = 'data.lon';
@endphp

<div
    wire:ignore
    x-data="{
        map: null,
        marker: null,

        lat() { return parseFloat(this.$wire.get(@js($latPath))) },
        lon() { return parseFloat(this.$wire.get(@js($lonPath))) },

        init() {
            // O Leaflet carrega em defer: esperar por ele antes de desenhar.
            if (typeof L === 'undefined') {
                setTimeout(() => this.init(), 100);

                return;
            }

            const start = Number.isFinite(this.lat()) && Number.isFinite(this.lon())
                ? [this.lat(), this.lon()]
                : [39.5, -8.0]; // Portugal continental, quando ainda não há coordenadas

            this.map = L.map(this.$refs.map, { attributionControl: false }).setView(start, Number.isFinite(this.lat()) ? 15 : 6);

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(this.map);
            // Só a linha que a licença do OpenStreetMap exige, sem a bandeira do Leaflet.
            L.control.attribution({ prefix: false }).addAttribution('&copy; OpenStreetMap').addTo(this.map);

            if (Number.isFinite(this.lat())) this.place(this.lat(), this.lon(), false);

            this.map.on('click', (e) => this.place(e.latlng.lat, e.latlng.lng));

            // O botão de pesquisa grava novas coordenadas: seguir o formulário.
            this.$watch('$wire.data.lat', () => this.follow());
            this.$watch('$wire.data.lon', () => this.follow());

            // O mapa é criado dentro de um separador escondido: forçar o redesenho.
            setTimeout(() => this.map.invalidateSize(), 300);
            new ResizeObserver(() => this.map.invalidateSize()).observe(this.$refs.map);
        },

        follow() {
            if (! Number.isFinite(this.lat()) || ! Number.isFinite(this.lon())) return;
            this.place(this.lat(), this.lon(), false);
            this.map.setView([this.lat(), this.lon()], 16);
        },

        place(lat, lon, write = true) {
            const icon = L.icon({
                iconUrl: @js(asset('vendor/leaflet/images/marker-icon.png')),
                iconRetinaUrl: @js(asset('vendor/leaflet/images/marker-icon-2x.png')),
                shadowUrl: @js(asset('vendor/leaflet/images/marker-shadow.png')),
                iconSize: [25, 41], iconAnchor: [12, 41], shadowSize: [41, 41],
            });

            if (! this.marker) {
                this.marker = L.marker([lat, lon], { draggable: true, icon }).addTo(this.map);
                this.marker.on('dragend', () => {
                    const p = this.marker.getLatLng();
                    this.save(p.lat, p.lng);
                });
            } else {
                this.marker.setLatLng([lat, lon]);
            }

            if (write) this.save(lat, lon);
        },

        save(lat, lon) {
            this.$wire.set(@js($latPath), lat.toFixed(7), false);
            this.$wire.set(@js($lonPath), lon.toFixed(7), false);
        },
    }"
    class="fi-fo-field-wrp"
>
    <div
        x-ref="map"
        class="w-full overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600"
        style="height: 20rem; z-index: 0"
    ></div>

    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
        Clique no mapa ou arraste o marcador para acertar a posição. Os quadrados do mapa vêm do OpenStreetMap.
    </p>
</div>
