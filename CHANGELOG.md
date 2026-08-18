# Changelog

Registo de tudo o que foi realizado, **commit a commit**, do mais recente para o
mais antigo. Cada entrada lista os ficheiros criados/alterados e o que cada um faz.
O hash de um commit é acrescentado na entrada seguinte (não é conhecido antes de
o commit existir).

---

## 2026-08-18 — Fase 1: scaffold e fundações

**Commit:** `Fase 1: layout base, tokens Tailwind, fontes locais, sitemap/robots, 404, testes`

### Dependências
- `livewire/livewire ^3` (componentes interativos server-rendered; traz o Alpine).
- `pestphp/pest ^3`, `pest-plugin-laravel`, `pest-plugin-livewire` (dev). Removido
  `phpunit/phpunit` como dependência direta (o Pest traz o seu). `tests/Pest.php` inicializado.
- npm: `npm install` do esqueleto (Vite 7, Tailwind 4, `@tailwindcss/vite`, `laravel-vite-plugin`).
  Build de produção gerado em `public/build/` (CSS 33 KB, JS 49 KB, não versionado).

### Identidade visual
- `resources/css/app.css` — reescrito:
  - `@font-face` para **Fraunces** (serif variável 300–600, eixos `opsz` e `SOFT`) e
    **Inter** (sans variável 400–600), normal e itálico, servidas de `public/fonts/`
    com `font-display: swap` e `unicode-range` latin.
  - `@theme` com os tokens da paleta (`sand-50/100/200`, `olive-600/700/900`, `ink`,
    `ink-muted`, `clay-400`) e reset da paleta por defeito do Tailwind (`--color-*: initial`),
    para que **só** existam estas cores; `--font-sans`/`--font-serif`; raios de canto
    limitados a 0/2/3 px; sombras reduzidas a uma linha capilar; `tracking-label`.
  - Camada base: fundo `sand-50`, texto `ink`, títulos em Fraunces light com
    `SOFT 50`, foco visível `olive-700`, `prefers-reduced-motion`, `[x-cloak]`.
  - Componentes: `.label` (eyebrow em maiúsculas), `.container-site`, `.btn-primary`,
    `.btn-secondary`, `.link`, `.price` (Fraunces, algarismos tabulares), `.field`.
- `public/fonts/` — 4 ficheiros woff2 (Fraunces normal/itálico, Inter normal/itálico),
  ~420 KB no total, subset latin, descarregados do Google Fonts (licença OFL).
- `public/images/og-default.jpg` — imagem Open Graph provisória (1200×630, gerada com GD:
  fundo `sand-50`, filete `olive-600`). **A substituir por fotografia real.**

### Layout e páginas
- `resources/views/components/layouts/app.blade.php` — layout base `<x-layouts.app>`:
  props `title`, `description`, `canonical`, `image`, `robots`; slot `head` para JSON-LD.
  `<title>` = "Página — Nome da agência"; canonical por defeito = URL atual; Open Graph
  e Twitter card; preload das duas fontes principais; link "saltar para o conteúdo".
- `resources/views/components/site/header.blade.php` — cabeçalho: marca, navegação
  (Comprar, Arrendar, Quanto vale a minha casa?, A agência, Contactos) com estado ativo
  e `aria-current`; menu móvel em Alpine com `aria-expanded` e fecho por Escape.
- `resources/views/components/site/footer.blade.php` — rodapé em `olive-900`
  (único bloco grande de verde): nome, morada, telefone e email da agência,
  **Licença AMI** (ou aviso "por atribuir" se vazio), colunas Imóveis / Legal,
  **Livro de Reclamações eletrónico**, política de privacidade, termos, cookies,
  redes sociais (só as preenchidas), copyright com ano dinâmico.
- `resources/views/components/site/search-form.blade.php` — pesquisa rápida
  (finalidade + texto livre) que faz GET para `/comprar` ou `/arrendar` com `?q=`;
  funciona sem JavaScript.
- `resources/views/pages/home.blade.php` — homepage estrutural (eyebrow, título,
  lead, pesquisa, CTAs). Destaques e zonas entram na Fase 4.
- `resources/views/pages/listing.blade.php` — esqueleto de `/comprar` e `/arrendar`
  (o Livewire de filtros substitui-o na Fase 4).
- `resources/views/pages/placeholder.blade.php` — página provisória com `noindex`
  para valuation, a agência, contactos, privacidade, termos e cookies.
- `resources/views/errors/404.blade.php` — 404 com pesquisa de imóveis; `500` e `503`
  no mesmo layout.
- `resources/views/seo/sitemap.blade.php` — template do sitemap XML.
- `resources/views/welcome.blade.php` e `public/robots.txt` **removidos** (o robots
  passou a ser dinâmico; um ficheiro estático teria prioridade sobre a rota).

### Rotas e controladores
- `routes/web.php` — `home` `/`, `buy` `/comprar`, `rent` `/arrendar`, `valuation`
  `/quanto-vale-a-minha-casa`, `about` `/a-agencia`, `contact` `/contactos`, `privacy`
  `/politica-de-privacidade`, `terms` `/termos-e-condicoes`, `cookies`
  `/politica-de-cookies`, `sitemap` `/sitemap.xml`, `robots` `/robots.txt`.
- `app/Http/Controllers/PageController.php` — páginas server-rendered acima.
- `app/Http/Controllers/SitemapController.php` — sitemap dinâmico (home, comprar,
  arrendar) com URLs de `route()`; na Fase 4 passa a incluir só imóveis ativos e zonas.
- `app/Http/Controllers/RobotsController.php` — `robots.txt` dinâmico: fora de produção
  `Disallow: /` (staging nunca indexado); em produção `Allow: /`, `Disallow: /livewire/`
  e `Sitemap:` apontado a `route('sitemap')`.

### Suporte
- `app/Support/AppUrl.php` — `forceFromConfig()`: fixa raiz **e esquema** do gerador de
  URLs a partir de `config('app.url')`. Garante canonical/sitemap/OG/emails corretos
  em CLI, queue e testes, e ignora o cabeçalho `Host` do pedido (host-header injection).
- `app/Support/AgencyCompliance.php` — `assertAmi(env)`: lança `RuntimeException`
  se o ambiente for `production` e `agency.ami` estiver vazio.
- `app/Providers/AppServiceProvider.php` — chama `AppUrl::forceFromConfig()` e
  `AgencyCompliance::assertAmi()` no `boot()`; `Vite::usePrefetchStrategy('aggressive')`.
- `resources/js/app.js` — só o bootstrap; o Alpine vem do Livewire; nenhum script de terceiros.

### Idioma
- `lang/pt/ui.php` — todas as strings da UI (navegação, rodapé, pesquisa, erros,
  homepage, listagens). Nenhum texto solto nos Blades.
- `lang/pt/validation.php` — mensagens de validação completas em pt-PT + nomes
  amigáveis dos campos dos formulários de lead.
- `lang/pt/pagination.php`, `lang/pt/auth.php`, `lang/pt/passwords.php` — traduzidos.
- `lang/en/` publicado pelo `lang:publish` e **removido** (só pt-PT).

### Testes (Pest) — 18 a passar, 1 ignorado fora de produção
- `tests/Feature/AgencyConfigTest.php` — AMI vazio/em branco falha em produção; AMI
  preenchido passa; vazio fora de produção é tolerado; teste que corre contra a
  configuração real da instância quando `APP_ENV=production`.
- `tests/Feature/PublicPagesTest.php` — homepage com canonical, AMI e Livro de
  Reclamações; aviso quando AMI vazio; `/comprar` e `/arrendar` separadas; páginas
  provisórias com `noindex`; 404 com pesquisa; **nenhum pedido a fonts.googleapis.com/gstatic**.
- `tests/Feature/SeoFilesTest.php` — sitemap derivado de `app.url` (com domínio
  fictício, sem `multifuturo.test`); robots bloqueia fora de produção; em produção
  permite e aponta o sitemap para `app.url`.
- Removidos os `ExampleTest` do esqueleto. Base de dados de testes: `testing` (PostgreSQL, criada pelo Sail).

### Notas
- Com `APP_URL=http://multifuturo.test` e a raiz forçada, **todos os links absolutos
  usam esse domínio** — a linha `127.0.0.1 multifuturo.test` no `hosts` é necessária
  para navegar localmente (não a consegui escrever: exige Administrador).

---

## 2026-08-18 — Passo 0: ambiente, configuração e `casafari:inspect`

**Commit:** `717561f` — `Passo 0: ambiente Sail, configuração CASAFARI/agência e comando casafari:inspect`
(sobre o `8bfdfd1 Initial commit` já existente no GitHub, cujo README de uma linha foi substituído)

### Ambiente
- Projeto **Laravel 12.66** criado com `composer create-project` dentro de Docker
  (`laravelsail/php83-composer`) — sem Composer instalado no Windows.
- **Laravel Sail** (`compose.yaml`): serviço `multifuturo.test` com PHP **8.3**
  (runtime alterado de 8.5 para 8.3), **PostgreSQL 16** (imagem alterada de 18 para
  16-alpine), Redis, Mailpit. Portas no host: app 80, Vite 5173, PostgreSQL **54320**,
  Redis **63790**, Mailpit 8025 (5432/6379 já estavam ocupados por outro projeto e
  por um PostgreSQL nativo).
- `sail.ps1` — wrapper PowerShell (`up`, `down`, `artisan`, `composer`, `npm`, `pest`,
  `shell`, `psql`, `redis`…) porque `./vendor/bin/sail` só corre em macOS/Linux/WSL2.
- `.env`: `APP_NAME="Multifuturo Imóveis"`, `APP_URL=http://multifuturo.test`,
  `APP_LOCALE=pt`, `APP_FALLBACK_LOCALE=pt`, `APP_FAKER_LOCALE=pt_PT`,
  `APP_TIMEZONE=Europe/Lisbon`, `DB_DATABASE=multifuturo`, `CACHE_STORE`/`SESSION_DRIVER`/
  `QUEUE_CONNECTION=redis`, `COMPOSE_PROJECT_NAME=multifuturo`, `APP_SERVICE`,
  `WWWUSER/WWWGROUP=1000`, blocos CASAFARI e AGENCY. `APP_KEY` gerada. Migrações base corridas.
- `config/app.php` — `timezone` lê `APP_TIMEZONE` (default Europe/Lisbon).
- Permissões de `storage/` e `bootstrap/cache` corrigidas (tinham ficado de root).

### Configuração
- `config/casafari.php` — `feed_url`, `lead_url` (default `https://insert.moonshapes.pt/lead`),
  `token`, `customer_origin_id`, `feed_timeout` (180 s), `feed_retries` (3),
  `feed_retry_delay_ms` (5000), `storage_dir` (`casafari`).
- `config/agency.php` — `name`, `ami`, `phone`, `email`, `address`, `social`
  (facebook/instagram/linkedin), `complaints_book_url`.
- `.env.example` — todas as variáveis acima, segredos vazios.

### Comando
- `app/Console/Commands/CasafariInspect.php` — `casafari:inspect [--file=] [--depth=] [--first-depth=]`:
  GET ao feed com timeout/retry da config, grava `storage/app/casafari/sample.xml`
  (fora do git), e imprime: hierarquia dos nós com contagens (XMLReader em streaming,
  `LIBXML_NONET`), nó de imóvel estimado e **contagem total**, estrutura completa do
  primeiro imóvel (atributos e valores truncados, repetições colapsadas), `lang` como
  atributo vs. elemento e valores encontrados, nº de URLs de imagem e média por imóvel.
  Sem URL no `.env` termina com erro claro e sugere `--file=`. Não escreve na BD.

### Documentação
- `README.md` — arranque do ambiente, portas, `hosts`, configuração, comandos.
- `CHANGELOG.md` — criado.
- `.gitignore`/`.editorconfig`/`.gitattributes` do esqueleto (LF, `.env` e `vendor/` fora).

### Decisões do Passo 0
- Tipografia escolhida: **Fraunces + Inter** (alternativas apresentadas: Cormorant
  Garamond + Work Sans; Instrument Serif + Instrument Sans). Contraste verificado:
  `ink/sand-50` 14,7:1, `sand-50/olive-600` 5,0:1, `ink-muted/sand-50` 5,1:1;
  `clay-400` sobre beje 2,9:1 → nunca em texto.
- Pendente: `CASAFARI_FEED_URL` (bloqueia inspeção do feed e estratégia de fotos).

---

## Histórico anterior à stack atual

- 2026-08-18 — Primeira abordagem em WordPress + DDEV (Fase 0 concluída) foi anulada
  por decisão do cliente; ambiente, DDEV e ficheiros removidos. Projeto reiniciado
  em Laravel com os requisitos acima.
