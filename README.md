# Multifuturo Imóveis — website

Site público da **Multifuturo Imóveis Lda** (mediação imobiliária, licença AMI).
Mostra a carteira de imóveis, capta contactos e devolve as leads ao CRM.

**Stack:** Laravel 12 · Livewire 3 · Tailwind 4 · Alpine · PostgreSQL 16 · Redis · Pest — em Docker via Laravel Sail.

| Documento | Conteúdo |
|---|---|
| [docs/CHECKLIST.md](docs/CHECKLIST.md) | Tudo o que falta para pôr o site online (dados a fornecer, requisitos de produção, go-live) |
| [CHANGELOG.md](CHANGELOG.md) | Histórico detalhado, commit a commit |

---

## Índice

1. [Arquitetura](#arquitetura)
2. [Começar (ambiente local)](#começar-ambiente-local)
3. [Configuração (.env)](#configuração-env)
4. [Sincronização com o CASAFARI](#sincronização-com-o-casafari)
5. [Leads](#leads)
6. [Frontend](#frontend)
7. [Privacidade, RGPD e conformidade](#privacidade-rgpd-e-conformidade)
8. [Testes e CI](#testes-e-ci)
9. [Mapa do código](#mapa-do-código)

---

## Arquitetura

**Princípio inegociável:** o site **nunca** lê do CRM em runtime.

```
CASAFARI CRM
   │  feed XML (Feedcruncher)                 API de leads
   ▼  casafari:sync (cron, hora a hora)          ▲
PostgreSQL (réplica local: properties) ─────► queue Redis ── job SendLeadToCasafari
   │                                             ▲
   ▼  leituras com cache Redis (tag)             │  grava local PRIMEIRO
Site público (Livewire, server-rendered) ── formulários (contacto/imóvel/avaliação)
```

- **Leitura:** o feed é replicado na tabela `properties` por um comando agendado.
  O site só lê da réplica, com cache Redis invalidada no fim de cada sync com alterações.
- **Escrita:** as leads gravam-se localmente primeiro e são enviadas ao CRM por um job
  com retries — se o CRM estiver em baixo, nada se perde.
- Server-rendered (SEO): sem SPA; o Livewire trata da interatividade das listagens.

## Começar (ambiente local)

Pré-requisito: Docker Desktop a correr.

> **Máquina nova:** copie o `.env.example` para `.env` **antes** do `composer install` —
> o `package:discover` arranca a aplicação e, sem `APP_ENV`, ela assume produção e
> recusa arrancar sem `AGENCY_AMI` (comportamento intencional).

O script `./vendor/bin/sail` só corre em macOS/Linux/WSL2. **No Windows** usa o wrapper
`sail.ps1` (PowerShell), que faz o mesmo sobre `docker compose`:

```powershell
.\sail.ps1 up -d              # arrancar tudo (app, PostgreSQL, Redis, Mailpit)
.\sail.ps1 artisan migrate    # criar as tabelas
npm install; npm run build    # assets (Node local)
.\sail.ps1 down               # parar
```

Abrir **http://localhost/**. Para usar `http://multifuturo.test/`, acrescentar ao
`hosts` (terminal Administrador): `127.0.0.1   multifuturo.test`.

**Portas no host** (5432/6379 estavam ocupadas nesta máquina):

| Serviço | Porta |
|---|---|
| Aplicação | 80 |
| Vite (HMR) | 5173 |
| PostgreSQL | **54320** |
| Redis | **63790** |
| Mailpit (email de teste) | **8025** (UI) |

**Atalhos do wrapper:** `artisan`, `composer`, `npm`, `pest`, `pint`, `tinker`,
`shell` (bash no container), `psql`, `redis`, `logs`, `ps`.

## Configuração (.env)

Copiar `.env.example` para `.env`. Grupos de variáveis:

| Grupo | Variáveis | Onde são lidas |
|---|---|---|
| CASAFARI — leitura | `CASAFARI_FEED_URL`, `CASAFARI_FEED_TIMEOUT/RETRIES/RETRY_DELAY_MS`, `CASAFARI_MIN_ITEMS` | `config/casafari.php` |
| CASAFARI — leads | `CASAFARI_LEAD_URL`, `CASAFARI_TOKEN`, `CASAFARI_CUSTOMER_ORIGIN_ID`, `CASAFARI_LEAD_ENTITY_TYPE` | `config/casafari.php` |
| Alertas | `CASAFARI_ALERT_EMAIL` (falhas do sync e leads não entregues) | `config/casafari.php` |
| Agência | `AGENCY_NAME`, **`AGENCY_AMI`**, `AGENCY_PHONE/EMAIL/ADDRESS`, `AGENCY_FACEBOOK/INSTAGRAM/LINKEDIN`, `AGENCY_COMPLAINTS_BOOK_URL`, `AGENCY_PRIVACY_POLICY_VERSION`, `AGENCY_HERO_IMAGE` | `config/agency.php` |
| Cookies | `CONSENT_COOKIE`, `CONSENT_DAYS`, `CONSENT_VERSION` | `config/consent.php` |

Regras importantes:

- **`AGENCY_AMI` é obrigatório por lei** (Lei n.º 15/2013). Em produção a aplicação
  **recusa arrancar** sem ele; há um teste que o garante.
- O domínio nunca é hardcoded: canonical, sitemap, Open Graph e emails derivam de
  `APP_URL` (forçado fora de `local` por `App\Support\AppUrl`).
- Ao alterar o texto da política de privacidade, atualizar `AGENCY_PRIVACY_POLICY_VERSION`
  — a versão é gravada em cada lead como prova do texto apresentado.

## Sincronização com o CASAFARI

```powershell
.\sail.ps1 artisan casafari:inspect                 # Passo 0: descreve o feed (hierarquia, contagem, lang)
.\sail.ps1 artisan casafari:sync                    # descarrega o feed e sincroniza
.\sail.ps1 artisan casafari:sync --dry-run          # só conta, não escreve
.\sail.ps1 artisan casafari:sync --force            # ignora o hash e reescreve tudo
.\sail.ps1 artisan casafari:sync --file=tests/Fixtures/casafari-feed.xml   # XML local
.\sail.ps1 artisan schedule:work                    # agendamento em local (hourlyAt 7)
```

Regras do motor (`app/Services/Casafari/`):

- **Upsert por `internal_id`** (chave única do CRM); parsing em streaming com `XMLReader`.
- **sha256 do nó XML** por imóvel: inalterado = só atualiza `synced_at` (nem `updated_at` mexe).
- **Slug estável**: gerado uma vez (`tipo-concelho-referência`), nunca recalculado — não se partem URLs indexados.
- **Nunca se apaga**: o que desaparece do feed passa a `is_active=false` (a ficha responde **410** com semelhantes); volta ao feed → reativa.
- **Guard crítico**: feed com 0 imóveis (ou abaixo de `CASAFARI_MIN_ITEMS`) aborta **antes**
  de desativar o que quer que seja. Erros de mapeamento também suspendem a desativação.
- **`Owner` nunca é lido** — removido do DOM antes de qualquer mapeamento (ver secção RGPD).
- Em produção: cron do sistema a correr `php artisan schedule:run` a cada minuto;
  falhas notificadas por email; log em `storage/logs/casafari-sync.log`.

> A nomenclatura dos nós em `config/casafari.php` (blocos `feed`/`mapping`) é **provisória**
> até o `casafari:inspect` correr sobre o feed real — só esse bloco mudará.

Os ficheiros em `storage/app/casafari/` contêm dados reais do feed (incluindo dados de
proprietários) e estão fora do git.

## Leads

Fluxo: formulário → `StoreLeadRequest` valida → **grava local primeiro** (`leads`) →
job `SendLeadToCasafari` na queue (tries=5, backoff 1 min→1 h).

- A API do CRM devolve HTTP 200 mesmo em falha: só `json.status === true` conta como
  sucesso; caso contrário o job relança e entra em retry; esgotadas as tentativas fica
  `failed` + email de alerta. A lead nunca se perde.
- **Anti-spam sem CAPTCHA:** honeypot (aceite em silêncio, sem gravar), timestamp
  assinado com a `APP_KEY` (rejeita submissões < 3 s ou forjadas), rate limiting por IP
  (5/min, 20/h — a chave é o hash do IP).
- **RGPD:** dois consentimentos separados (`IncludeOptIn`/`IncludeMailing`), desmarcados
  por defeito, nunca forçados; `policy_version` e `ip_hash` (HMAC, nunca o IP) gravados.
- Worker necessário: `php artisan queue:work` (em produção, como serviço).

## Frontend

```powershell
npm run dev      # Vite com HMR (porta 5173)
npm run build    # produção → public/build (não versionado)
```

**Identidade:** paleta bege/azeitona e tipografia **Fraunces + Inter** servidas
localmente de `public/fonts/` (RGPD: zero pedidos a CDNs). Os tokens vivem em
`resources/css/app.css` (`@theme`) — nunca usar hex soltos nos componentes.
Layout de referência: template "Consultor Imobiliário (Elegante)" (estrutura e tom, não cópia).

**Rotas públicas:**

| Rota | Conteúdo |
|---|---|
| `/` | hero (`AGENCY_HERO_IMAGE` ou capa do 1.º destaque), pesquisa, destaques, sobre, porquê, zonas, banda de contacto |
| `/comprar` · `/arrendar` | listagem Livewire; filtros na query string (`q`, `tipo`, `tipologia`, `concelho`, `freguesia`, `preco_min/max`, `area_min`, `caracteristicas[]`, `ordenar`, `page`) — URLs partilháveis, 12/página |
| `/imoveis/{slug}` | ficha: galeria/lightbox, JSON-LD `RealEstateListing`, mapa OSM **só com `gmap_visible`** e carregado ao clicar; inativo → **410** com semelhantes |
| `/zonas` · `/zonas/{concelho}[/{freguesia}]` | páginas de zona derivadas da carteira; texto editorial opcional na tabela `zones` |
| `/favoritos` | favoritos em localStorage (sem registo); o servidor só renderiza os cartões pedidos |
| `/quanto-vale-a-minha-casa` | lead magnet de avaliação (lead sem imóvel associado) |
| `/contactos` · `/a-agencia` · políticas | institucionais e legais |
| `/sitemap.xml` · `/robots.txt` | dinâmicos, derivados de `APP_URL`; sitemap só com imóveis ativos; robots bloqueia tudo fora de produção |

**Cache:** todas as leituras de imóveis passam por `App\Support\PropertyCache`
(tag `properties`, TTL 1 h), invalidada pelo evento `PropertiesSynced` quando o sync altera algo.

## Privacidade, RGPD e conformidade

- **`Owner` (proprietário) nunca é persistido** — o nó é removido do DOM antes de
  qualquer leitura; não existe coluna; testes procuram fugas em todas as colunas.
  Do consultor (Broker) guardam-se só nome e foto.
- **`gmap_visible=false`** (compromisso contratual): as coordenadas nunca saem do
  servidor — nem HTML, nem JSON-LD (o acessor `coordinates` devolve `null`).
- **Cookies:** banner próprio com consentimento granular (necessários / análise /
  marketing), recusa com o mesmo peso visual, escolhas num cookie first-party (180 dias).
  Scripts de terceiros escrevem-se `<x-consent-script category="…">` → `type="text/plain"`
  até ao opt-in. Hoje não há nenhum, e um teste garante que nenhuma página faz pedidos externos.
- **Mapa:** iframe OpenStreetMap criado apenas ao clicar em "Mostrar mapa".
- **Rodapé legal:** AMI, Livro de Reclamações eletrónico, políticas, "Gerir cookies".
- Textos legais em `lang/pt/legal.php` (minutas — carecem de revisão jurídica).
- Todo o conteúdo do feed é tratado como não confiável: tipos forçados e limitados no
  mapper, escape nas views, `LIBXML_NONET` (sem XXE), URLs validados.

## Testes e CI

```powershell
.\sail.ps1 pest           # 104 testes (Pest 3, PostgreSQL "testing")
.\sail.ps1 pint           # estilo de código (--test para só verificar)
```

- Os testes correm contra PostgreSQL (o schema usa `jsonb` e índices GIN — não é testável em SQLite).
- CI em GitHub Actions (`.github/workflows/tests.yml`): Pint + Pest com PostgreSQL 16
  e Redis em cada push/PR; em falha, as linhas relevantes saem como annotations.
- Cobertura das regras críticas: guard do feed vazio, Owner, slugs estáveis,
  `gmap_visible`, lead local com CRM em baixo, `status=false` em HTTP 200, AMI em
  produção, XSS/XXE, rate limiting.

## Mapa do código

```
app/
├── Console/Commands/     CasafariInspect (diagnóstico do feed) · CasafariSync (sincronização)
├── Enums/                BusinessType · LeadSource · LeadStatus
├── Events/               PropertiesSynced (fim do sync → invalida cache)
├── Http/
│   ├── Controllers/      Page · Property (ficha, JSON-LD, 410) · Zone · Favorites · Lead · Sitemap · Robots
│   └── Requests/         StoreLeadRequest (validação + anti-spam)
├── Jobs/                 SendLeadToCasafari (queue, retries, status===true)
├── Listeners/            FlushPropertyCache
├── Livewire/             PropertyListing (filtros na query string, paginação, cache)
├── Models/               Property (scopes, coordinates, generateSlug) · Lead · Zone
├── Notifications/        LeadDeliveryFailed
├── Services/Casafari/    FeedClient · FeedReader · PropertyMapper · PropertySyncer · SyncResult
└── Support/              AppUrl · AgencyCompliance (AMI) · Format · PropertyCache · Zones · helpers

config/    casafari.php (feed/mapping PROVISÓRIO) · agency.php · consent.php
lang/pt/   ui.php (toda a UI) · legal.php (políticas) · validation/pagination/auth/passwords
resources/
├── css/app.css           tokens da marca (@theme), fontes locais
├── js/                   app.js (favoritos, fallback de imagens) · consent.js (cookies)
└── views/
    ├── components/       layouts/app · site/{header,footer,search-form,consent-banner}
    │                     property/{card,image,gallery} · lead-form · consent-script
    ├── livewire/         property-listing
    ├── pages/            home · listing · property · property-gone · zones · zone ·
    │                     favorites · contact · valuation · legal
    └── errors/           404 (com pesquisa) · 500 · 503
tests/
├── Feature/              AgencyConfig · PublicPages · SeoFiles · PropertySchema ·
│                         CasafariSync · Leads · Frontend · Legal · PropertyMapper · EdgeCases
└── Fixtures/             casafari-feed.xml (PROVISÓRIA — substituir por excerto real anonimizado)
```
