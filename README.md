# Multifuturo Imóveis — website

Site público da Multifuturo Imóveis Lda (mediação imobiliária). Réplica local da
carteira do CASAFARI CRM em PostgreSQL, sincronizada por cron; leads devolvidas ao
CRM via queue. Laravel 12 · Livewire 3 · Tailwind 4 · PostgreSQL 16 · Redis.

## Ambiente local (Laravel Sail / Docker)

O script `./vendor/bin/sail` só corre em macOS/Linux/WSL2. No Windows usa o
wrapper `sail.ps1` (PowerShell), que faz o mesmo sobre `docker compose`:

```powershell
.\sail.ps1 up -d            # arrancar (app em http://localhost — ou http://multifuturo.test com hosts)
.\sail.ps1 down
.\sail.ps1 artisan migrate
.\sail.ps1 artisan casafari:inspect        # Passo 0 — inspeciona o feed XML
.\sail.ps1 pest                             # testes
.\sail.ps1 shell / psql / redis
```

Portas expostas no host: app **80**, Vite 5173, PostgreSQL **54320**, Redis **63790**,
Mailpit UI **8025** (as portas 5432/6379 estão ocupadas por outros serviços nesta máquina).

`multifuturo.test` requer uma linha no `hosts` (terminal Administrador):

```
127.0.0.1   multifuturo.test
```

## Configuração

Copiar `.env.example` para `.env` e preencher:

- `CASAFARI_FEED_URL`, `CASAFARI_TOKEN`, `CASAFARI_CUSTOMER_ORIGIN_ID` — `config/casafari.php`
- `AGENCY_AMI`, `AGENCY_PHONE`, `AGENCY_EMAIL`, … — `config/agency.php`

O AMI é obrigatório em produção (há um teste que falha se estiver vazio).

## Comandos

| Comando | Função |
|---|---|
| `casafari:inspect [--file=]` | Descarrega o feed (ou lê um XML local), guarda `storage/app/casafari/sample.xml` e imprime hierarquia, primeiro imóvel, contagem e formato do `lang`. Não escreve na BD. |

Os ficheiros em `storage/app/casafari/` contêm dados reais do feed (incluindo dados
de proprietários) e estão fora do git.

## Frontend

```powershell
npm install
npm run build        # produção → public/build (não versionado)
npm run dev          # Vite com HMR (porta 5173)
```

Fontes servidas localmente de `public/fonts/` (Fraunces + Inter, woff2). Os tokens de
cor vivem em `resources/css/app.css` (`@theme`) — nunca usar hex soltos nos componentes.

## Testes

```powershell
.\sail.ps1 pest
.\sail.ps1 pint --test    # estilo de código
```

## Sincronização com o CASAFARI

```powershell
.\sail.ps1 artisan casafari:sync              # descarrega o feed e sincroniza
.\sail.ps1 artisan casafari:sync --dry-run    # só conta, não escreve
.\sail.ps1 artisan casafari:sync --force      # ignora o hash e reescreve tudo
.\sail.ps1 artisan casafari:sync --file=tests/Fixtures/casafari-feed.xml   # XML local
.\sail.ps1 artisan schedule:work              # corre o agendamento em local (hourlyAt 7)
```

Regras: upsert por `internal_id`; sha256 do nó XML salta imóveis inalterados; slug
nunca muda; o que desaparece do feed passa a `is_active=false` (nunca se apaga);
feed vazio (ou abaixo de `CASAFARI_MIN_ITEMS`) aborta **antes** de desativar;
`Owner` nunca é lido. Em produção, o cron do sistema corre `php artisan schedule:run`
a cada minuto; falhas vão por email para `CASAFARI_ALERT_EMAIL`.

A estrutura do feed em `config/casafari.php` (`feed`/`mapping`) é **provisória** até
o `casafari:inspect` correr sobre o feed real.

## Frontend público (Fase 4)

| Rota | Conteúdo |
|---|---|
| `/` | hero (foto de `AGENCY_HERO_IMAGE` ou capa do 1.º destaque), pesquisa, destaques (`is_featured`, completa com recentes), sobre, porquê, zonas, banda de contacto |
| `/comprar`, `/arrendar` | listagem Livewire com filtros na query string (`q`, `tipo`, `tipologia`, `concelho`, `freguesia`, `preco_min`, `preco_max`, `area_min`, `caracteristicas[]`, `ordenar`, `page`) |
| `/imoveis/{slug}` | ficha; inativo → **410** com semelhantes; mapa só com `gmap_visible` e carregado ao clicar |
| `/zonas`, `/zonas/{concelho}`, `/zonas/{concelho}/{freguesia}` | páginas de zona; texto editorial opcional na tabela `zones` (`city_slug`, `locality_slug`, `title`, `intro`, `body`, `cover_url`) |
| `/favoritos` | favoritos em localStorage; o servidor só renderiza os cartões pedidos (`?slugs=`) |

Cache: tudo o que lê imóveis passa por `App\Support\PropertyCache` (tag `properties`, TTL 1 h)
e é invalidado no fim de cada sync com alterações (`FlushPropertyCache`).
