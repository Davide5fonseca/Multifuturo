/*
 * Movimento do site — o que dá vida às páginas sem lhes tirar a calma.
 *
 * Três coisas, todas ligadas ao scroll e todas opcionais:
 *   [data-reveal]    aparece ao entrar no ecrã (sobe e ganha opacidade)
 *   [data-parallax]  desloca-se devagar enquanto se desce (fotografias)
 *   [data-count]     conta até ao número ao ser visto
 *
 * Regras que não se negoceiam:
 *   · Sem JavaScript nada disto existe — e nada fica escondido, porque o
 *     estado inicial só se aplica debaixo de `html.js`, que é posto aqui.
 *   · Com "reduzir movimento" ligado no sistema, tudo aparece de uma vez:
 *     nem transições, nem parallax, nem contagens.
 */

const quieto = window.matchMedia('(prefers-reduced-motion: reduce)');

/** Só depois disto é que o CSS esconde o que há-de aparecer. */
document.documentElement.classList.add('js');

/* ------------------------------------------------------------------ revelar */

let observador = null;

function revelar(el) {
    el.classList.add('is-visible');
}

function observarRevelacoes(raiz = document) {
    const alvos = raiz.querySelectorAll('[data-reveal]:not(.is-visible)');
    if (!alvos.length) return;

    if (quieto.matches || !('IntersectionObserver' in window)) {
        alvos.forEach(revelar);
        return;
    }

    observador ??= new IntersectionObserver(
        (entradas) => {
            entradas.forEach((entrada) => {
                if (!entrada.isIntersecting) return;
                revelar(entrada.target);
                observador.unobserve(entrada.target);
            });
        },
        // Começa um pouco antes de entrar mesmo no ecrã: chega "já feito".
        // threshold 0: basta tocar. Com uma fração, um bloco muito alto — ou um
        // que ainda não tenha altura — nunca chegava a contar como visível.
        { rootMargin: '0px 0px -8% 0px', threshold: 0 },
    );

    alvos.forEach((el) => observador.observe(el));
}

/*
 * Escada: numa grelha marcada com [data-reveal-stagger], cada filho entra um
 * pouco depois do anterior. O atraso é escrito no elemento para o CSS o usar.
 */
function escalonar(raiz = document) {
    raiz.querySelectorAll('[data-reveal-stagger]').forEach((grupo) => {
        const passo = Number(grupo.dataset.revealStagger) || 90;
        [...grupo.children].forEach((filho, i) => {
            const alvo = filho.matches('[data-reveal]') ? filho : filho.querySelector('[data-reveal]');
            if (alvo && !alvo.style.transitionDelay) {
                alvo.style.transitionDelay = `${Math.min(i, 8) * passo}ms`;
            }
        });
    });
}

/* ----------------------------------------------------------------- parallax */

let camadas = [];
let pendente = false;

function medirParallax() {
    camadas = [...document.querySelectorAll('[data-parallax]')].map((el) => ({
        el,
        forca: Number(el.dataset.parallax) || 0.15,
        pai: el.parentElement,
    }));
}

function moverParallax() {
    pendente = false;
    const altura = window.innerHeight;

    camadas.forEach(({ el, forca, pai }) => {
        const caixa = pai.getBoundingClientRect();
        if (caixa.bottom < 0 || caixa.top > altura) return;
        // Do centro do ecrã para fora: -1 acima, +1 abaixo.
        const relativo = (caixa.top + caixa.height / 2 - altura / 2) / altura;
        el.style.transform = `translate3d(0, ${(relativo * forca * 100).toFixed(2)}px, 0)`;
    });
}

function aoScroll() {
    if (pendente) return;
    pendente = true;
    window.requestAnimationFrame(moverParallax);
}

/* ---------------------------------------------------------------- contagens */

function contar(el) {
    const fim = Number(el.dataset.count);
    if (!Number.isFinite(fim)) return;

    if (quieto.matches) {
        el.textContent = String(fim);
        return;
    }

    const duracao = 1200;
    const inicio = performance.now();

    const passo = (agora) => {
        const t = Math.min((agora - inicio) / duracao, 1);
        // Trava no fim, para o número assentar em vez de bater.
        const suave = 1 - Math.pow(1 - t, 3);
        el.textContent = String(Math.round(fim * suave));
        if (t < 1) window.requestAnimationFrame(passo);
    };

    window.requestAnimationFrame(passo);
}

function observarContagens() {
    const alvos = document.querySelectorAll('[data-count]');
    if (!alvos.length || !('IntersectionObserver' in window)) {
        alvos.forEach(contar);
        return;
    }

    const io = new IntersectionObserver((entradas) => {
        entradas.forEach((e) => {
            if (!e.isIntersecting) return;
            contar(e.target);
            io.unobserve(e.target);
        });
    }, { threshold: 0.4 });

    alvos.forEach((el) => io.observe(el));
}

/* ------------------------------------------------------------------ arranque */

function arrancar() {
    escalonar();
    observarRevelacoes();
    observarContagens();

    if (!quieto.matches) {
        medirParallax();
        moverParallax();
        window.addEventListener('scroll', aoScroll, { passive: true });
        window.addEventListener('resize', () => { medirParallax(); aoScroll(); }, { passive: true });
    }
}

document.addEventListener('DOMContentLoaded', arrancar);

/*
 * O Livewire troca pedaços da página (filtros, mais resultados): o que entrar
 * de novo tem de ser observado, senão ficava invisível para sempre.
 */
document.addEventListener('livewire:navigated', arrancar);
document.addEventListener('livewire:update', () => {
    escalonar();
    observarRevelacoes();
    if (!quieto.matches) medirParallax();
});
