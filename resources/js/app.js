// Ponto de entrada JS. O Alpine é injetado pelo Livewire 3 — não o importar aqui.
// Sem tracking, sem chamadas externas: qualquer script de terceiros fica atrás
// do consentimento de cookies (Fase 6).
import './bootstrap';
import './consent';

/*
 * Favoritos em localStorage — sem registo, sem servidor. Guardamos só os slugs;
 * a página /favoritos pede ao servidor os cartões desses slugs.
 */
const FAVORITES_KEY = 'multifuturo:favoritos';
const RECENT_KEY = 'multifuturo:vistos';
const RECENT_MAX = 12;

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

document.addEventListener('alpine:init', () => {
    window.Alpine.data('suggestions', suggestions);

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
