/*
 * Consentimento de cookies — sem CMP de terceiros.
 *
 * Estado: cookie first-party (nome em window.MF_CONSENT.cookie) com JSON
 * { v, necessary: true, analytics: bool, marketing: bool, ts }.
 *
 * Scripts não essenciais ficam no HTML como <script type="text/plain"
 * data-consent="analytics"> e só são executados quando a categoria é aceite —
 * antes disso o navegador nunca os interpreta nem faz pedidos.
 */
const cfg = window.MF_CONSENT ?? { cookie: 'mf_consent', days: 180, version: 1, categories: ['analytics', 'marketing'] };

function readCookie() {
    const raw = document.cookie.split('; ').find((c) => c.startsWith(cfg.cookie + '='));
    if (!raw) return null;
    try {
        const data = JSON.parse(decodeURIComponent(raw.slice(cfg.cookie.length + 1)));
        return data && data.v === cfg.version ? data : null;
    } catch {
        return null;
    }
}

function writeCookie(state) {
    const value = encodeURIComponent(JSON.stringify(state));
    const maxAge = cfg.days * 24 * 60 * 60;
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${cfg.cookie}=${value}; Max-Age=${maxAge}; Path=/; SameSite=Lax${secure}`;
}

function activateScripts(state) {
    document.querySelectorAll('script[type="text/plain"][data-consent]').forEach((el) => {
        const category = el.dataset.consent;
        if (!state[category] || el.dataset.activated) return;
        const s = document.createElement('script');
        [...el.attributes].forEach((a) => {
            if (!['type', 'data-consent'].includes(a.name)) s.setAttribute(a.name, a.value);
        });
        s.type = 'text/javascript';
        s.text = el.text;
        el.dataset.activated = '1';
        el.after(s);
    });
    document.dispatchEvent(new CustomEvent('mf:consent', { detail: state }));
}

document.addEventListener('alpine:init', () => {
    window.Alpine.store('consent', {
        open: false,
        customizing: false,
        state: null,
        choices: { analytics: false, marketing: false },

        init() {
            this.state = readCookie();
            if (this.state) {
                this.choices = { analytics: !!this.state.analytics, marketing: !!this.state.marketing };
                activateScripts(this.state);
            } else {
                this.open = true;
            }
        },

        has(category) {
            return !!(this.state && this.state[category]);
        },

        save(partial) {
            const state = { v: cfg.version, necessary: true, ts: Date.now() };
            cfg.categories.forEach((c) => (state[c] = !!partial[c]));
            this.state = state;
            this.choices = { analytics: state.analytics, marketing: state.marketing };
            writeCookie(state);
            activateScripts(state);
            this.open = false;
            this.customizing = false;
        },

        acceptAll() {
            const all = {};
            cfg.categories.forEach((c) => (all[c] = true));
            this.save(all);
        },

        rejectAll() {
            this.save({});
        },

        saveChoices() {
            this.save(this.choices);
        },

        manage() {
            this.customizing = true;
            this.open = true;
        },
    });
});
