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

document.addEventListener('alpine:init', () => {
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
