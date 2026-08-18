# Multifuturo Imóveis — o que falta para pôr o site online

Estado em 2026-08-18: Passo 0 (parcial) e Fases 1 a 7 concluídas. 104 testes a passar, CI verde.
Este documento lista **tudo** o que ainda é preciso, quem o faz, e o que desbloqueia.

---

## A. Informação e acessos que só o cliente pode dar

### A1. Feed do CASAFARI (bloqueia o Passo 0 e a sincronização real)

| O quê | Onde se pede | Onde se coloca | Formato |
|---|---|---|---|
| URL do feed XML (Feedcruncher) | Suporte CASAFARI / gestor de conta, a partir de `admin.casafaricrm.com` | `.env` → `CASAFARI_FEED_URL=` | URL completo `https://…` (pode incluir token na query string) |

O que acontece a seguir (meu): corro `casafari:inspect`, comparo com o bloco `mapping` de
`config/casafari.php`, corrijo nomes de nós, substituo `tests/Fixtures/casafari-feed.xml`
por um excerto **real anonimizado**, decido fotos (hotlink vs. espelho) com o número real de
imóveis, corro o primeiro sync real e afino `CASAFARI_MIN_ITEMS` (recomendo ~50 % da carteira).

### A2. API de leads do CASAFARI

| O quê | `.env` |
|---|---|
| Token da API de leads | `CASAFARI_TOKEN=` |
| CustomerOriginID atribuído à agência | `CASAFARI_CUSTOMER_ORIGIN_ID=` |
| URL do endpoint (se for diferente do default) | `CASAFARI_LEAD_URL=https://insert.moonshapes.pt/lead` |
| Documentação da API (PDF/link) | — para confirmar `EntityType` (`CASAFARI_LEAD_ENTITY_TYPE`, hoje `Lead`) e se `Message` deve levar o contexto (origem, referência, dados de avaliação) como está |
| Email interno para alertas de sync/leads falhadas | `CASAFARI_ALERT_EMAIL=` |

Sem token, as leads ficam guardadas em `pending` (nada se perde) e são enviadas quando o
token entrar.

### A3. Dados da agência (rodapé, políticas, contactos, "A agência")

| Dado | `.env` | Nota |
|---|---|---|
| Nome legal | `AGENCY_NAME="Multifuturo Imóveis Lda"` | já está |
| **Licença AMI** (só o número) | `AGENCY_AMI=` | **obrigatório**: em produção a aplicação recusa arrancar sem ele |
| Telefone | `AGENCY_PHONE="+351 …"` | |
| Email geral | `AGENCY_EMAIL=` | |
| Morada da sede | `AGENCY_ADDRESS="Rua …, CP Localidade"` | |
| Redes sociais | `AGENCY_FACEBOOK=`, `AGENCY_INSTAGRAM=`, `AGENCY_LINKEDIN=` | vazias = não aparecem |
| Livro de Reclamações | `AGENCY_COMPLAINTS_BOOK_URL` | default `https://www.livroreclamacoes.pt/` — manter |
| Versão da política de privacidade | `AGENCY_PRIVACY_POLICY_VERSION=2026-08-18` | mudar quando o texto mudar |

### A4. Imagens

| Ficheiro | Onde | Especificação |
|---|---|---|
| Fotografia do hero da homepage | `public/images/hero.jpg` + `.env` `AGENCY_HERO_IMAGE=/images/hero.jpg` | horizontal, mín. 1920×1080, JPG ≤ 400 KB (comprimido), sem texto |
| Imagem de partilha (Open Graph) | `public/images/og-default.jpg` (substituir o placeholder) | 1200×630, JPG |
| Favicon / logótipo | `public/favicon.ico` (+ SVG/PNG se existir logótipo) | o atual é o do Laravel |
| Logótipo para o cabeçalho (opcional) | a combinar | hoje é a palavra "Multifuturo." em Fraunces |

### A5. Textos

| Texto | Onde vive | Estado |
|---|---|---|
| Homepage: título/lead do hero, "Sobre", "Porquê" (3 argumentos), banda de contacto | `lang/pt/ui.php` → `home_sections` | provisório (escrito por mim) |
| Página "A agência" | `lang/pt/legal.php` → `about` | provisório |
| Depoimentos (opcional, o template de referência tem) | a criar se houver 2–3 reais | não existe |
| Conteúdo editorial por zona (concelho/freguesia) | tabela `zones` | vazia — é o que gera tráfego orgânico de cauda longa |
| Emails de resposta automática ao cliente (opcional) | a criar | não existe (hoje só há confirmação no ecrã) |

### A6. Legal

- Revisão das minutas por quem responde pela conformidade: política de privacidade,
  termos e condições, política de cookies (`lang/pt/legal.php`).
- Confirmar que os contactos do consultor (Broker) **não** devem ser públicos (hoje só nome e foto).
- Confirmar o texto dos dois consentimentos dos formulários (`lang/pt/ui.php` → `lead.consent_*`).

### A7. Domínio e alojamento

- **Domínio** a registar (o site lê tudo de `APP_URL`; nada está hardcoded).
- **Onde vai ficar alojado** — preciso desta decisão para preparar o deploy (ver secção C).
- Serviço de email transacional (SMTP) para os alertas e futuros emails: fornecedor + credenciais
  (`MAIL_*` no `.env`).

---

## B. Trabalho meu que depende do de cima

| Depois de… | Faço |
|---|---|
| A1 | `casafari:inspect` → mapping real → fixture real → estratégia de fotos → primeiro sync real → afinar `min_items` |
| A2 | teste ponta a ponta de uma lead real; confirmar `EntityType`/`Message` |
| A3–A5 | nada de código — só verificar rodapé, políticas e homepage com os dados reais |
| A5 (zonas) | seeder para carregar textos de zona a partir de um ficheiro simples (Markdown/CSV) |
| A7 | deploy completo (secção C) |

## Trabalho meu que **não** depende de ninguém (posso fazer já)

1. Seeder/estrutura para conteúdo das zonas (fica pronto a receber texto).
2. Favicon e imagem OG genéricos com a identidade (até haver logótipo real).
3. Página 500 mais útil e testes de acessibilidade automatizados básicos.
4. Comando `leads:retry` para reenviar leads `failed` depois de o CRM voltar.
5. Preparar os ficheiros de deploy (Dockerfile de produção / config de servidor) assim que se souber o alojamento.

---

## C. Requisitos de produção (para quem alojar)

**Software**
- PHP **8.3** (ou 8.4) com extensões: `pdo_pgsql`, `pgsql`, `redis`, `mbstring`, `intl`, `xml`, `dom`, `xmlreader`, `gd`, `curl`, `zip`, `bcmath`
- **PostgreSQL 16** (usa `jsonb` e índices GIN)
- **Redis** (cache, sessão e queue)
- Nginx (ou Caddy) com HTTPS (Let's Encrypt), raiz em `public/`
- Composer 2, Node 22 (só para compilar assets — pode ser feito no CI)

**Processos permanentes**
- Cron do sistema: `* * * * * php /caminho/artisan schedule:run >> /dev/null 2>&1` (corre o `casafari:sync` de hora a hora)
- Worker da queue como serviço (Supervisor/systemd): `php artisan queue:work redis --tries=5 --timeout=90` (envio de leads)

**Variáveis de produção** (`.env`)
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://dominio.pt`, `APP_KEY` gerada
- `DB_*` (PostgreSQL), `REDIS_*`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`
- `MAIL_*` (SMTP real), todos os `CASAFARI_*` e `AGENCY_*` acima
- `CASAFARI_MIN_ITEMS` ajustado ao tamanho da carteira

**Após cada deploy**
- `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`
- `php artisan migrate --force`, `php artisan optimize` (config/route/view/event cache)
- `php artisan queue:restart`
- Backups diários da base de dados (as leads vivem lá; a carteira é recuperável do feed)

**Verificação de arranque**
- Sem `AGENCY_AMI` a aplicação **não arranca** em produção (por desenho)
- `robots.txt` só permite indexação com `APP_ENV=production`

---

## D. Checklist de go-live (na ordem)

1. [ ] A3 preenchido (`AGENCY_*`), sobretudo o AMI
2. [ ] A1 e A2 preenchidos; `casafari:inspect` corrido; mapping ajustado; primeiro sync real OK
3. [ ] Estratégia de fotos decidida e aplicada
4. [ ] A4 imagens reais; A5 textos revistos; A6 minutas aprovadas (e `AGENCY_PRIVACY_POLICY_VERSION` atualizada se mudaram)
5. [ ] Domínio + alojamento (C) prontos; DNS a apontar; HTTPS ativo
6. [ ] `.env` de produção completo; `APP_ENV=production`; email SMTP a funcionar (teste com uma lead falhada de propósito → chega o alerta?)
7. [ ] Cron e worker da queue a correr; `casafari:sync` agendado a passar (ver `storage/logs/casafari-sync.log`)
8. [ ] Uma lead de teste ponta a ponta: formulário → BD → job → aparece no CRM
9. [ ] `sitemap.xml` e `robots.txt` corretos no domínio real; Google Search Console configurada
10. [ ] Banner de cookies testado (aceitar / recusar / gerir); nenhuma chamada externa antes do consentimento
11. [ ] Backups da BD ativos e testado um restauro
