// Ponto de entrada JS. O Alpine é injetado pelo Livewire 3 — não o importar aqui.
// Sem tracking, sem chamadas externas: qualquer script de terceiros fica atrás
// do consentimento de cookies (Fase 6).
import './bootstrap';
import './consent';
import './motion';

/*
 * Favoritos em localStorage — sem registo, sem servidor. Guardamos só os slugs;
 * a página /favoritos pede ao servidor os cartões desses slugs.
 */
const FAVORITES_KEY = 'multifuturo:favoritos';
const RECENT_KEY = 'multifuturo:vistos';
const RECENT_MAX = 12;
const COMPARE_KEY = 'multifuturo:comparar';
const COMPARE_MAX = 3;

/*
 * Pesquisa com sugestões: enquanto se escreve, o servidor devolve concelhos,
 * freguesias e imóveis que correspondem (SearchSuggestController). Teclado
 * completo (setas, Enter, Escape) e sem JavaScript o formulário submete-se
 * na mesma. Nada é guardado — é só leitura da carteira publicada.
 */
function suggestions(endpoint) {
    return {
        open: false,
        items: [],
        active: -1,
        loading: false,
        timer: null,
        controller: null,

        onInput(value) {
            const q = value.trim();
            this.active = -1;
            clearTimeout(this.timer);

            if (q.length < 2) {
                this.items = [];
                this.open = false;
                return;
            }

            this.timer = setTimeout(() => this.fetch(q), 220);
        },

        async fetch(q) {
            this.controller?.abort();
            this.controller = new AbortController();
            this.loading = true;

            try {
                const url = endpoint + '?q=' + encodeURIComponent(q) + '&f=' + encodeURIComponent(this.$refs.tipo?.value === 'rent' ? 'rent' : 'buy');
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: this.controller.signal });
                if (!response.ok) return;
                this.items = (await response.json()).items ?? [];
                this.open = true;
            } catch {
                /* pedido cancelado ou rede em baixo: o formulário continua a funcionar */
            } finally {
                this.loading = false;
            }
        },

        move(delta) {
            if (!this.open || !this.items.length) return;
            this.active = (this.active + delta + this.items.length) % this.items.length;
        },

        /** Enter: se houver sugestão escolhida vai-se lá; senão submete a pesquisa. */
        choose(event) {
            if (this.open && this.active >= 0 && this.items[this.active]) {
                event.preventDefault();
                window.location.href = this.items[this.active].url;
            }
        },

        close() {
            this.open = false;
            this.active = -1;
        },
    };
}

/*
 * Leaflet a pedido: os ficheiros vivem no nosso servidor (public/vendor/leaflet)
 * e só são carregados quando há mesmo um mapa para desenhar. Uma promessa
 * partilhada evita carregá-los duas vezes na mesma página.
 */
let leafletPromise = null;

function loadLeaflet(assets) {
    if (window.L) return Promise.resolve();
    if (leafletPromise) return leafletPromise;

    leafletPromise = new Promise((resolve, reject) => {
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = assets.css;
        document.head.appendChild(css);

        const js = document.createElement('script');
        js.src = assets.js;
        js.onload = resolve;
        js.onerror = reject;
        document.head.appendChild(js);
    });

    return leafletPromise;
}

/** O alfinete da marca, com as imagens do nosso storage. */
function marker(assets) {
    return window.L.icon({
        iconUrl: assets.icon,
        iconRetinaUrl: assets.icon2x,
        shadowUrl: assets.shadow,
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        shadowSize: [41, 41],
    });
}

/** Atribuição mínima que a licença do OpenStreetMap exige. */
function attribution(map) {
    const link = '<a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>';
    window.L.control.attribution({ prefix: false }).addAttribution('&copy; ' + link).addTo(map);
}

/*
 * Mapa da ficha de imóvel: um alfinete, desenhado assim que a página abre.
 * Só existe quando o proprietário autorizou mostrar a localização.
 */
function propertyMap(assets, lat, lon) {
    return {
        async init() {
            await loadLeaflet(assets);
            if (this.map) return;
            const pos = [lat, lon];
            this.map = window.L.map(this.$refs.map, { scrollWheelZoom: false, attributionControl: false }).setView(pos, 15);
            window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(this.map);
            attribution(this.map);
            window.L.marker(pos, { icon: marker(assets) }).addTo(this.map);
        },
        map: null,
    };
}

/*
 * Mapa dos resultados da listagem: um alfinete por imóvel com localização
 * pública, com o cartãozinho (foto, título, preço) ao clicar. O mapa só é
 * construído quando o visitante o abre, e volta a desenhar-se sempre que os
 * resultados mudam (filtros, scroll infinito).
 */
function resultsMap(assets, points) {
    return {
        open: false,
        map: null,
        layer: null,
        points,

        async toggle() {
            this.open = !this.open;
            if (!this.open) return;
            await loadLeaflet(assets);
            this.$nextTick(() => this.draw());
        },

        /**
         * Os resultados mudaram (filtro novo ou mais um bloco): o Livewire
         * reconstrói o elemento que traz os pontos e este avisa o mapa.
         */
        update(points) {
            this.points = points;
            if (this.open && this.map) this.$nextTick(() => this.draw());
        },

        draw() {
            const points = this.points;

            if (!this.map) {
                this.map = window.L.map(this.$refs.map, { scrollWheelZoom: false, attributionControl: false });
                window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(this.map);
                attribution(this.map);
            }

            this.layer?.remove();
            if (!points.length) return;

            this.layer = window.L.layerGroup(points.map((p) => {
                return window.L.marker([p.lat, p.lon], { icon: marker(assets), title: p.title })
                    .bindPopup(this.card(p));
            })).addTo(this.map);

            this.map.fitBounds(points.map((p) => [p.lat, p.lon]), { padding: [40, 40], maxZoom: 15 });
            this.map.invalidateSize();
        },

        /** Cartão do alfinete construído com o DOM: nada de HTML colado à mão. */
        card(p) {
            const link = document.createElement('a');
            link.href = p.url;
            link.className = 'block w-44 no-underline';

            if (p.photo) {
                const img = document.createElement('img');
                img.src = p.photo;
                img.alt = '';
                img.loading = 'lazy';
                img.className = 'mb-2 h-24 w-full rounded object-cover';
                link.appendChild(img);
            }

            const title = document.createElement('span');
            title.textContent = p.title;
            title.className = 'block text-sm font-medium leading-snug text-ink';
            link.appendChild(title);

            const price = document.createElement('span');
            price.textContent = p.price;
            price.className = 'mt-1 block text-sm text-ink-muted';
            link.appendChild(price);

            return link;
        },
    };
}

/*
 * Fotografias da abertura a alternar. Troca de imagem de X em X tempo, com um
 * esbatimento lento; os pontos deixam escolher à mão e param a rotação (quem
 * escolhe manda). Com "reduzir movimento" ligado, fica na primeira e quieta.
 */
function slideshow(total, intervalo = 5000) {
    return {
        atual: 0,
        total,
        timer: null,

        init() {
            if (this.total < 2) return;
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            this.comecar();
            // Numa página em segundo plano não vale a pena andar a trocar imagens.
            document.addEventListener('visibilitychange', () => {
                document.hidden ? this.parar() : this.comecar();
            });
        },

        comecar() {
            this.parar();
            this.timer = setInterval(() => { this.atual = (this.atual + 1) % this.total; }, intervalo);
        },

        parar() {
            if (this.timer) clearInterval(this.timer);
            this.timer = null;
        },

        /** Escolha manual: mostra a imagem e deixa de rodar sozinha. */
        ir(i) {
            this.atual = i;
            this.parar();
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('slideshow', slideshow);
    window.Alpine.data('suggestions', suggestions);
    window.Alpine.data('propertyMap', propertyMap);
    window.Alpine.data('resultsMap', resultsMap);

    window.Alpine.store('favorites', {
        slugs: [],

        init() {
            try {
                const raw = window.localStorage.getItem(FAVORITES_KEY);
                this.slugs = raw ? JSON.parse(raw).filter((s) => typeof s === 'string') : [];
            } catch {
                this.slugs = [];
            }
        },

        has(slug) {
            return this.slugs.includes(slug);
        },

        toggle(slug) {
            this.slugs = this.has(slug) ? this.slugs.filter((s) => s !== slug) : [...this.slugs, slug];
            this.persist();
        },

        remove(slug) {
            this.slugs = this.slugs.filter((s) => s !== slug);
            this.persist();
        },

        /*
         * Poda os favoritos que já não existem no site (imóveis vendidos,
         * retirados ou apagados). Sem isto, um slug morto ficava preso no
         * localStorage para sempre: o coração contava-o e ele nunca saía.
         * Chamado pela página de favoritos com a lista que o servidor devolveu.
         */
        prune(valid) {
            const keep = this.slugs.filter((s) => valid.includes(s));
            if (keep.length !== this.slugs.length) {
                this.slugs = keep;
                this.persist();
            }
        },

        get count() {
            return this.slugs.length;
        },

        persist() {
            try {
                window.localStorage.setItem(FAVORITES_KEY, JSON.stringify(this.slugs));
            } catch {
                /* armazenamento indisponível (modo privado) — os favoritos vivem só nesta sessão */
            }
        },
    });

    /*
     * Comparador: até três imóveis escolhidos, guardados no aparelho do
     * visitante. A página /comparar lê-os e pede ao servidor os dados para a
     * tabela; nada disto sai daqui sem ser por escolha de quem navega.
     */
    window.Alpine.store('compare', {
        slugs: [],
        max: COMPARE_MAX,
        full: false,

        init() {
            try {
                const raw = window.localStorage.getItem(COMPARE_KEY);
                this.slugs = raw ? JSON.parse(raw).filter((s) => typeof s === 'string').slice(0, COMPARE_MAX) : [];
            } catch {
                this.slugs = [];
            }
        },

        has(slug) {
            return this.slugs.includes(slug);
        },

        /** Tirar funciona sempre; pôr só até ao limite — daí o aviso "full". */
        toggle(slug) {
            if (this.has(slug)) {
                this.slugs = this.slugs.filter((s) => s !== slug);
            } else {
                if (this.slugs.length >= COMPARE_MAX) {
                    this.full = true;
                    setTimeout(() => (this.full = false), 2500);
                    return;
                }
                this.slugs = [...this.slugs, slug];
            }
            this.persist();
        },

        clear() {
            this.slugs = [];
            this.persist();
        },

        /** Tira da lista os imóveis que já não existem no site. */
        prune(valid) {
            const keep = this.slugs.filter((s) => valid.includes(s));
            if (keep.length !== this.slugs.length) {
                this.slugs = keep;
                this.persist();
            }
        },

        get count() {
            return this.slugs.length;
        },

        persist() {
            try {
                window.localStorage.setItem(COMPARE_KEY, JSON.stringify(this.slugs));
            } catch {
                /* armazenamento indisponível — a escolha vive só nesta sessão */
            }
        },
    });

    /*
     * Imóveis vistos recentemente — só os slugs, no aparelho do visitante, sem
     * contas nem cookies. A ficha regista-se a si própria ao abrir; a home e as
     * fichas mostram a lista pedindo os cartões ao servidor.
     */
    window.Alpine.store('recent', {
        slugs: [],

        init() {
            try {
                const raw = window.localStorage.getItem(RECENT_KEY);
                this.slugs = raw ? JSON.parse(raw).filter((s) => typeof s === 'string').slice(0, RECENT_MAX) : [];
            } catch {
                this.slugs = [];
            }
        },

        /** O mais recente fica à frente e nunca se repete. */
        push(slug) {
            if (typeof slug !== 'string' || slug === '') return;
            this.slugs = [slug, ...this.slugs.filter((s) => s !== slug)].slice(0, RECENT_MAX);
            try {
                window.localStorage.setItem(RECENT_KEY, JSON.stringify(this.slugs));
            } catch {
                /* armazenamento indisponível — a lista vive só nesta sessão */
            }
        },
    });
});

/*
 * Imagens do CRM: se um URL falhar, troca pelo placeholder local em vez de
 * mostrar o ícone de imagem partida.
 */
document.addEventListener(
    'error',
    (event) => {
        const el = event.target;
        if (el instanceof HTMLImageElement && el.dataset.fallback && el.src !== el.dataset.fallback) {
            el.src = el.dataset.fallback;
            el.removeAttribute('srcset');
        }
    },
    true,
);
