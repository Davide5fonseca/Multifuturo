# Changelog

Registo de tudo o que foi realizado, **commit a commit**, do mais recente para o
mais antigo. Cada entrada abre com a **data e hora** do commit em destaque (a verde
azeitona — renderizado como expressão matemática, que é a única forma de cor que o
GitHub aceita em Markdown), seguida do hash e do título, e lista os ficheiros
criados/alterados e o que cada um faz. Horas em Europe/Lisbon. Os commits que apenas
atualizam este ficheiro não têm entrada própria.

---

## Separador "Geral" com as listas do CRM

$${\color{#6B7248}\textsf{2026-08-24 · 11:34}}$$

**Commit:** `e2047c4` — `Backoffice: listas do separador Geral iguais às do CRM`

As quatro listas do separador *Geral* passam a ter **exactamente as opções do CRM da
CASAFARI, pela mesma ordem** — a equipa não tem de reaprender nada ao mudar de sistema.

| Campo | Opções |
|---|---|
| **Conservação** | Em construção · Não aplicável · Novo · Projecto · Ruína · Usado |
| **Tipo de propriedade** | Apartamento · Casa de campo · Chalet · Empreendimento · Loja / comércio · Moradia · Moradia em Banda · Penthouse · Prédio · Quinta · Ruína · Terreno · Terreno rústico · Terreno urbano |
| **Tipologia** | Não aplicável · T0 a T10 |
| **Tipo de negócio** | Venda · Arrendamento ao ano · Trespasse · Permuta · Arrendamento curto prazo / férias · Arrendamento / venda |

O tipo de propriedade e a tipologia ficam **pesquisáveis** (listas longas). As listas
continuam a juntar valores que já existam na base de dados, para nenhuma ficha importada
ficar com "valor inválido".

### O que o tipo de negócio arrastou
O site só tem **duas listagens** — *Comprar* e *Arrendar* — e o enum `BusinessType` só
tinha duas finalidades. Passou a ter as seis, e **cada uma declara em que listagem entra**:

| Finalidade | Onde aparece | Preço |
|---|---|---|
| Venda | Comprar | valor |
| Trespasse | Comprar | valor |
| Permuta | Comprar | valor |
| Arrendamento ao ano | Arrendar | **/mês** |
| Arrendamento curto prazo / férias | Arrendar | **/mês** |
| Arrendamento / venda | **nas duas** | valor |

Trespasse e permuta ficam em *Comprar* porque é aí que quem procura os vai encontrar. O
"arrendamento / venda" aparece nas duas listagens, e o rasto da ficha aponta para
*Comprar*. **Se preferir outro critério, diga — é uma linha a mudar** no
`BusinessType::listings()`.

Mudou em consequência: os scopes `forSale`/`forRent`, o filtro e as opções da listagem, o
`Format::price` (o "/mês" fica só para os arrendamentos puros), o rasto da ficha e os
rótulos em `lang/pt`.

### Testes
Quatro novos em `tests/Feature/BusinessTypeTest.php` — o mapeamento das seis finalidades,
o que cada listagem pública mostra, o preço mensal e os rótulos. **145 a passar**, Pint
limpo.

### Por confirmar
- A **tipologia** vai até **T10** (o menu do CRM tinha barra de deslocamento e só se viam
  até T4). Se for mais alto, é um número a mudar.
- No **tipo de propriedade**, entre *Penthouse* e *Prédio* não se via o menu completo. Se
  faltar algum, acrescenta-se.

---

## Correcção: apagar um imóvel rebentava

$${\color{#6B7248}\textsf{2026-08-24 · 10:58}}$$

**Commit:** `62bdfa0` — `Backoffice: corrigir erro ao apagar um imóvel`

Encontrado ao verificar, a pedido do cliente, se uma ficha criada no backoffice
aparecia logo no site.

### O problema
Apagar um imóvel no backoffice dava **erro de violação de chave estrangeira**. O
`PropertyObserver` tentava escrever a linha "Apagada" no histórico já **depois** de a
linha do imóvel ter desaparecido — e a chave estrangeira, que é em cascata, tinha
entretanto levado com todo o histórico daquela ficha. Resultado: o imóvel era apagado, mas
o utilizador via um erro e **não ficava registo de quem o tinha apagado**.

### A correcção
- `property_activities.property_id` passa a **poder ser nulo**. A cascata mantém-se: o
  histórico de um imóvel continua a morrer com ele — não faria sentido encher o quadro
  "Actualizações" de linhas órfãs de fichas que já não existem.
- A **linha da eliminação** fica sem imóvel, com a **referência e o título no detalhe**
  (`REF-001 — Moradia T3 em Espinho`), para a dashboard continuar a registar quem apagou o
  quê e quando.
- Coluna "Referência" do quadro passa a mostrar `—` nessas linhas.

### Testes
Dois novos, agora **141 a passar**:
- apagar um imóvel não rebenta e deixa registo com o utilizador que o fez;
- o quadro "Actualizações" mostra a linha de um imóvel apagado.

---

## Site servido em `http://localhost/multifuturo`

$${\color{#6B7248}\textsf{2026-08-20 · 16:44}}$$

**Commit:** `c3ca041` — `Dev: servir o site em http://localhost/multifuturo`

A pedido do cliente, o site deixa de estar na raiz e passa a viver numa subpasta.

| | Endereço |
|---|---|
| **Site** | **http://localhost/multifuturo** |
| **Backoffice** | **http://localhost/multifuturo/admin** |

`http://localhost/` reencaminha para `/multifuturo/`.

### Como

O Sail serve a aplicação com `php artisan serve`, que **só sabe responder na raiz** — não
tem forma de servir numa subpasta. Foi preciso pôr um **nginx à frente**:

- `docker/nginx/default.conf` + `docker/nginx/proxy-headers.inc`, e um serviço `proxy`
  novo no `compose.yaml`. O nginx fica com a porta 80 do host (a aplicação deixa de a
  publicar) e **retira o prefixo** antes de entregar o pedido:
  `localhost/multifuturo/comprar` → aplicação: `/comprar`.
- `APP_URL=http://localhost/multifuturo`, e o `AppUrl::forceFromConfig()` passa a ser
  aplicado também em desenvolvimento quando o `APP_URL` indica uma subpasta — é o que faz
  os links, os assets, o canonical e os formulários saírem já com o prefixo.
- **`X-Forwarded-Prefix` confiado** em `bootstrap/app.php`, só a partir de endereços de
  rede privada. Sem isto o `$request->url()` chegava sem o prefixo e os **URLs assinados**
  do Livewire — o upload de fotografias e documentos no backoffice — falhavam a validação
  da assinatura. Foi verificado: o endereço assinado responde `419` (falta o CSRF) e não
  `403` (assinatura inválida).
- O Livewire constrói alguns endereços a partir da raiz (`/livewire/update`,
  `/livewire/livewire.js`); o nginx encaminha-os tal e qual, sem mexer no prefixo.

### Verificado
- Todas as páginas do site e do backoffice respondem `200` na subpasta.
- Os links, o canonical, o Open Graph, as fontes e os assets do Vite e do Filament saem
  todos com `/multifuturo` — e todos respondem `200`.
- Uma ida e volta real ao `/livewire/update` a partir da página de login do backoffice
  devolve `200` com o estado correcto: o Livewire funciona na subpasta.
- Velocidade inalterada: ~**0,15 s** por página através do nginx.
- **Os testes continuam a correr na raiz** — `APP_URL` fixo no `phpunit.xml`, para não
  ficarem presos à subpasta escolhida. 139 testes a passar, Pint limpo.

### Para mudar de subpasta
Três sítios: `APP_URL` no `.env`, e o prefixo em `docker/nginx/default.conf` e
`docker/nginx/proxy-headers.inc`.

---

## Desempenho do ambiente de desenvolvimento

$${\color{#6B7248}\textsf{2026-08-20 · 16:12}}$$

**Commit:** `ac28ec7` — `Dev: ligar o OPcache no container — páginas de 3s para 0,15s`

As páginas demoravam **2 a 3 segundos** a abrir em `http://localhost`.

### Causa
O Sail serve o site com `php artisan serve`, ou seja pelo **SAPI de linha de comandos**,
onde o **OPcache vem desligado por omissão**. Como o projeto está no disco do Windows e é
montado no container, cada pedido tinha de voltar a ler e a compilar centenas de ficheiros
PHP através dessa ponte de ficheiros. (Um ficheiro estático servia em 11 ms — a rede e o
Docker não eram o problema.)

### Correção
- `docker/php-dev.ini`, montado pelo `compose.yaml` em
  `/etc/php/8.3/cli/conf.d/`: liga o OPcache no CLI, com 256 MB e 20 000 ficheiros, e
  aumenta a *realpath cache* para 8 MB.
- `opcache.revalidate_freq = 10` — escolhido por medição: com o valor por omissão (2 s) as
  páginas eram rápidas mas com picos de 1,5 a 2,6 s de dez em dez pedidos, porque
  revalidava todos os ficheiros através da montagem lenta. Com 10 s o tempo fica estável e
  **as alterações ao código continuam a aplicar-se sem reiniciar** (até 10 s; para as ver
  de imediato, `.\sail.ps1 restart`).

**Resultado:** `/` e `/comprar` passam de ~3 s para **~0,15 s**.

### Também
- `APP_URL` passa de `http://multifuturo.test` para `http://localhost` — o domínio local
  não estava no ficheiro `hosts`, pelo que os URLs gerados fora do pedido (sitemap,
  emails, JSON-LD) apontavam para um endereço que não resolvia.
- README: secção **"Onde corre, e em que endereços"** com site, backoffice, Mailpit,
  PostgreSQL e Redis, mais a nota de desempenho.

---

## Calendário da agenda e limpeza dos dados fictícios

$${\color{#6B7248}\textsf{2026-08-20 · 15:28}}$$

**Commit:** `5d06032` — `Backoffice: calendário da agenda e remoção de todos os dados fictícios`

### Calendário
- `app/Filament/Pages/Calendario.php` + `resources/views/filament/pages/calendario.blade.php`
  — página `/admin/calendario` **feita à medida, sem package novo** (evita mais uma
  dependência e permite usar as cores da marca):
  - vistas **mês / semana / dia**, navegação anterior/seguinte e botão "Hoje";
  - **filtros** por utilizador e tipo de evento, e "mostrar concluídos";
  - eventos coloridos por tipo (telefonema, visita, reunião, tarefa, lembrete) com hora e
    título, que **abrem o registo** ao clicar; concluídos aparecem riscados;
  - dia de hoje destacado, dias de outros meses esbatidos, "+N mais" salta para a vista
    diária, e **legenda** das cores.
- `resources/css/filament/admin/theme.css` + `->viteTheme(...)` — tema próprio do painel:
  sem ele, as classes Tailwind usadas nas views à medida não eram compiladas (o painel
  aparecia sem grelha nem cores).

### Remoção dos dados fictícios
A pedido do cliente, **todo o conteúdo de demonstração foi eliminado**:
- Seeders `DemoContentSeeder` e `DemoCrmSeeder` **apagados** do repositório.
- Base de dados local limpa: 10 imóveis, 6 leads, 5 clientes, 5 eventos, 2 zonas
  editoriais e as visualizações/histórico de demonstração.
- O site e o backoffice ficam vazios, à espera dos dados reais introduzidos no `/admin`.
- As *factories* (usadas só pelos testes) e a fixture XML de testes mantêm-se — não são
  conteúdo do site.

### Nota de ambiente
O `make:filament-theme` correu `npm install` dentro do container, o que substituiu os
binários nativos do Windows; a partir de agora **os assets compilam-se no container**
(`.\sail.ps1 npm run build`). Documentado no README.

- 139 testes a passar, Pint limpo.

---

## Backoffice — módulos de CRM e dashboard

$${\color{#6B7248}\textsf{2026-08-20 · 15:16}}$$

**Commit:** `accb842` — `Backoffice: módulos de CRM — clientes, leads com pipeline, agenda, histórico e visualizações`

A pedido do cliente, a dashboard do backoffice passa a ter os mesmos quadros e
funcionalidades do painel do antigo CRM.

### Estrutura de dados
- `database/migrations/2026_08_20_160000_create_crm_tables.php`:
  - **`contacts`** — clientes (comprador / proprietário / ambos), contactos, concelho,
    notas, preferências (jsonb) e responsável.
  - **`events`** — agenda: telefonema, visita, reunião, tarefa, lembrete; data/hora,
    concluído, ligações a cliente e imóvel.
  - **`property_activities`** — histórico por imóvel (tipo + detalhe + autor).
  - **`property_views`** — contador de visualizações **por imóvel e por dia**.
  - **`leads`** ganham `kind` (angariação / comprador), `status` (pipeline),
    `priority`, `assigned_to` e `contact_id`, mais notas internas.
- Enums: `LeadKind`, `LeadStage` (pipelines distintos por tipo: prospeção → contactar
  proprietário → avaliação → angariado, e recebido → qualificação → visita → proposta →
  fechado), `LeadPriority` (normal/alta/urgente), `ContactKind`, `EventType`.
- Models `Contact`, `Event`, `PropertyActivity`, `PropertyView` (com `record()` atómico).

### Dashboard (widgets)
- **Leads de angariação** e **Leads de compradores** — dois quadros lado a lado com
  utilizador, data, cliente (e referência do imóvel), estado e prioridade em badge;
  mostram apenas as que estão **em aberto**; clicar abre a lead.
- **Actualizações** — histórico automático: nova ficha, alteração de preço
  (`520 001 € → 520 000 €`), publicada/vendida/retirada, edição; com miniatura,
  referência e autor.
- **Agenda — próximos eventos** — o que está por fazer, com **atrasados a vermelho** e
  ação rápida "Concluir".
- **Visualizações de imóveis (30 dias)** — gráfico de linha na cor da marca.

### Automatismos
- `app/Observers/PropertyObserver.php` — escreve o histórico em cada criação, alteração
  de preço, mudança de estado ou edição, sempre com o utilizador autenticado.
- `PropertyController::show()` — regista a visualização da ficha. **Privacidade:** só um
  contador por imóvel e por dia, sem IP, sem cookies, sem identificador de visitante —
  métrica agregada, não rastreio (e por isso não depende do consentimento de cookies).
  Fichas indisponíveis (410) não contam.

### Menu
- **Clientes** e **Agenda** como secções próprias, a par de Imóveis, Pedidos do site e
  Zonas.
- `database/seeders/DemoCrmSeeder.php` — clientes, leads nos vários estados, agenda com
  atrasados e 30 dias de visualizações, para ver a dashboard preenchida (nunca em produção).

### Testes
- `tests/Feature/CrmDashboardTest.php` — 11 testes: separação dos dois quadros; só leads
  em aberto; concluir evento na agenda; histórico por ordem; soma diária do gráfico;
  registo automático de criação/preço/estado/edição e autor; contagem de visualizações
  **sem dados do visitante**; 410 não conta; cliente agrega leads e eventos; pipelines
  por tipo.
- Total: **139 testes a passar**, Pint limpo.

### Por fazer nesta área
- Calendário mensal (a vista de mês do CRM) — precisa de um package de calendário para
  Filament, a aprovar.
- "Datas a lembrar" como quadro próprio (hoje são eventos do tipo *lembrete* na agenda).

---

## Backoffice — o painel que substitui o CRM

$${\color{#6B7248}\textsf{2026-08-20 · 14:32}}$$

**Commit:** `90f1f4d` — `Backoffice: painel de gestão em /admin (Filament 4) substitui o CRM`

**Mudança de arquitetura (decisão do cliente):** deixa de haver CRM externo. A equipa
passa a introduzir os imóveis num backoffice próprio, que escreve na **mesma base de
dados** que o site já lia — sem sincronização, sem feed, sem API de leads.

```
ANTES:  CRM CASAFARI ──feed XML──► PostgreSQL ──► Website ──API──► CRM (leads)
AGORA:  Backoffice /admin ────────► PostgreSQL ──► Website
                                         └──► email à agência (leads)
```

### Dependência
- `filament/filament:^4.0` (aprovado pelo cliente) — painel de administração
  open-source do ecossistema Laravel, já em português.

### Painel
- `app/Providers/Filament/AdminPanelProvider.php` — painel em `/admin`, marca
  "Multifuturo.", favicon do site, widget de informação do Filament removido, e
  **escala de cor azeitona definida à mão** (50–950 com `600 = #6B7248` e
  `700 = #565C39`): o gerador automático do Filament produzia verdes fluorescentes
  a partir do nosso hex.
- `app/Models/User.php` — implementa `FilamentUser`; `canAccessPanel()` devolve true
  para qualquer conta existente (não há registo público: criar a conta **é** a
  autorização). Sem isto o Filament devolvia 403 fora do ambiente local.

### Imóveis (substitui a ficha do CRM)
- `PropertyForm` — formulário por secções: **Identificação** (referência única,
  finalidade, tipo), **Conteúdo** (título, descrição, **upload de fotografias**
  múltiplas com reordenação e editor de imagem — a 1.ª é a capa, guardadas em
  `storage/app/public/imoveis`; características com sugestões), **Preço e áreas**,
  **Localização** (com o interruptor `gmap_visible` explicado), **Edifício**
  (certificado energético **obrigatório**), **Ligações externas** (colapsada) e
  **Publicação** (publicado/destaque/exclusivo, consultor, data do anúncio).
- `PropertiesTable` — capa, referência, título, finalidade (badge), concelho, preço
  formatado, **toggles rápidos** de publicado/destaque, filtros por finalidade,
  estado e concelho, pesquisa por referência/título/concelho.
- `CreateProperty` / `EditProperty` — geram `internal_id` (`BO-` + ULID),
  `slug` (tipo-concelho-referência, **gerado uma vez e nunca recalculado** ao editar,
  para não partir URLs indexados) e `payload_hash`; **invalidam a cache do site** em
  cada gravação; a edição **preserva fotografias externas** (importadas do CRM, no CDN
  deles) que o componente de upload não gere.
- Os selects de tipo/estado/certificado **juntam os valores já existentes na base**
  às opções sugeridas — sem isto, editar um imóvel importado do CRM (com "usado" ou
  "B-") falhava com "valor inválido".

### Pedidos do site (leads)
- `LeadResource` — caixa de entrada **só de leitura** (não se criam pedidos à mão),
  badge com a contagem dos últimos 7 dias, detalhe com mensagem, dados de avaliação,
  **consentimentos RGPD e versão da política**; ação de apagar para spam.
- `app/Notifications/NewLeadReceived.php` — **email imediato à agência**
  (`AGENCY_EMAIL`) por cada pedido, com nome, contactos, imóvel, mensagem e
  consentimentos; enviado pela queue.
- **Removidos:** `SendLeadToCasafari` (job), `LeadDeliveryFailed` (notificação),
  `leads:retry` (comando) e o agendamento do sync em `routes/console.php` — deixaram
  de fazer sentido sem CRM.

### Zonas
- `ZoneResource` — o conteúdo editorial das páginas de zona passa a editar-se no
  browser (o comando `zones:import` mantém-se para carregamentos em lote).

### Importação do antigo CRM
- `app/Services/Casafari/` e `casafari:sync --file=` ficam disponíveis para uma
  **importação pontual** da exportação XML do CRM — sem agendamento.

### Testes
- `tests/Feature/AdminPanelTest.php` (6) — login acessível; `/admin`, `/admin/properties`,
  `/admin/leads` e `/admin/zones` exigem autenticação; utilizador autenticado vê as
  listagens; não há rota de criação de pedidos; `/admin` fora do sitemap.
- `tests/Feature/BackofficePropertyTest.php` (6) — criação com campos técnicos gerados;
  **imóvel criado aparece imediatamente no site** (e na listagem certa); edição não
  recalcula o slug; fotografias externas preservadas; referência única; cache invalidada.
- `tests/Feature/LeadsTest.php` reescrito para o email à agência (sem CRM).
- Total: **122 testes a passar**, Pint limpo.

### Notas
- Requer `php artisan storage:link` (fotografias) e um worker `queue:work` (emails).
- O formulário será afinado quando o cliente enviar os prints dos campos que quer.

---

## Demo — remoção das imagens do template de referência

$${\color{#6B7248}\textsf{2026-08-19 · 17:55}}$$

**Commit:** `ea37472` — `Demo: remover as imagens copiadas do template de referência`

A pedido do cliente, eliminou-se do site tudo o que tinha sido copiado do template de
referência (wh-1112). O único material copiado eram as **imagens** — os textos
(depoimentos, descrições dos imóveis demo, secções da homepage) foram sempre escritos
de raiz e mantêm-se.

- `public/images/demo/` **eliminada** (fotografias das casas, retrato da consultora e
  hero — material do template Wix; nunca esteve no git).
- `database/seeders/DemoContentSeeder.php` — os 10 imóveis demo ficam **sem fotografias**
  (`photos: []`): cartões, galeria e hero mostram o placeholder/variante bege da marca;
  a consultora fica sem foto. Reexecutado sobre a base local.
- `.env` local: `AGENCY_HERO_IMAGE` limpo — o hero da homepage volta à variante bege
  sem fotografia (comportamento já previsto desde a Fase 4).
- `.gitignore`: entrada `public/images/demo/` removida (a pasta já não existe).
- Verificado com screenshot da homepage; 117 testes e Pint verdes.

---

## UI — geometria suavizada

$${\color{#6B7248}\textsf{2026-08-19 · 12:43}}$$

**Commit:** `4f87ae8` — `UI: suavizar a geometria — raios 8/12/16/24 px, chips e badges em pílula`

A pedido do cliente ("ainda estão todos muito quadrados"), a escala de raios de canto
passou de 2–3 px (quase invisível) para uma escala moderna e ainda sóbria:

- `resources/css/app.css` — tokens novos: `sm` 6 px, `md` 8 px (default), `lg` 12 px,
  `xl` 16 px, `2xl` 24 px, `full`; **campos** (`.field`) e **botões** a `rounded-md`.
- **`rounded-xl` (16 px):** cartão de imóvel (com `overflow-hidden` — a fotografia
  acompanha os cantos), imagem principal da galeria, formulário de lead, caixa do mapa,
  caixas de estado vazio, mosaicos das zonas (homepage e `/zonas`, agora com moldura),
  capa da página de zona.
- **`rounded-2xl` (24 px):** banda CTA da homepage (passa de faixa `border-y` a cartão).
- **Pílulas (`rounded-full`):** badge "Exclusivo", contador "Ver todas as fotografias",
  chips de freguesia nas páginas de zona, contador de favoritos no cabeçalho; botão de
  favorito e a bolinha das comodidades ficam circulares.
- Paginação: quadrados suavizados (`rounded-md`); miniaturas da galeria `rounded-lg`.
- 117 testes e Pint verdes; verificado com screenshots de `/comprar` e da ficha.

---

## UI — micro-interações nos botões e links

$${\color{#6B7248}\textsf{2026-08-19 · 12:32}}$$

**Commit:** `00b7e51` — `UI: botões e links com micro-interações — varrimento diagonal de cor sólida no hover, press físico no clique, sublinhado que cresce`

- `resources/css/app.css` — botões mais dinâmicos, fiéis à direção (sem gradientes decorativos nem sombras):
  - **`.btn-primary`**: no hover, um varrimento diagonal a 115° desliza a cor de `olive-600` para `olive-900` (duas cores sólidas num background deslizante — em repouso e em movimento nunca se vê um degradê);
  - **`.btn-secondary`**: o contorno enche-se de azeitona da esquerda para a direita e o texto passa a areia;
  - todos os botões: o `letter-spacing` abre ligeiramente no hover ("respira") e o `:active` tem um press físico (`translateY(1px) + scale(.99)` com transição curta);
  - **`.link`**: sublinhado que cresce da esquerda com easing suave, em vez do sublinhado estático;
  - o `prefers-reduced-motion` global continua a desligar todas as transições.
- Nota técnica: no Tailwind 4 não se pode `@apply` uma classe própria — a base partilhada dos botões passou a seletor agrupado (`.btn, .btn-primary, .btn-secondary`).
- Verificado com página de pré-visualização dos três estados (normal/hover/pressionado) em screenshot headless.

---

## Homepage — secção de depoimentos

$${\color{#6B7248}\textsf{2026-08-19 · 11:59}}$$

**Commit:** `5318515` — `Homepage: secção de depoimentos (estrutura do template de referência) com testemunhos provisórios de demonstração`

- `resources/views/pages/home.blade.php` — nova secção "O que dizem os nossos clientes"
  entre o *Sobre* e o *Porquê* (a posição do template de referência): três citações em
  Fraunces com filete azeitona à esquerda, autor e contexto.
- `lang/pt/ui.php` — `home_sections.testimonials*`; os três testemunhos são **PROVISÓRIOS**
  (demonstração): substituir por testemunhos reais **com autorização escrita** de cada
  cliente antes de publicar, ou esvaziar a lista — a secção desaparece sozinha.
- Fecha o paralelo estrutural com o template wh-1112: hero → sobre → depoimentos →
  porquê → contacto. (Segue-se `c3c4440`, correção de estilo Pint — fins de linha CRLF.)

---

## Conteúdo de demonstração (imagens do template)

$${\color{#6B7248}\textsf{2026-08-19 · 11:33}}$$

**Commit:** `1289ef0` — `Demo: seeder de conteúdo com as imagens do template de referência (dev-only)`

- `database/seeders/DemoContentSeeder.php` — a pedido do cliente, povoou-se o site com conteúdo de demonstração usando as fotografias do template de referência (wh-1112): **10 imóveis fictícios** realistas (moradia com piscina em Cascais exclusiva e em destaque, T3 com vista de mar, T2 em Campo de Ourique, V3 em Oeiras, T1 e T2 para arrendamento, quinta em Sintra com `gmap_visible=false`, terreno em Sesimbra, loja em Algés, penthouse com preço sob consulta), com descrições, características, 3 consultores (o retrato do template como foto da consultora) e **2 zonas editoriais** (Cascais e Lisboa).
- As imagens vivem em `public/images/demo/` (recortes variados das 3 fotos de imóveis do template + retrato + hero), **fora do git** — são material do template Wix, servem só para demonstração local e nunca vão para produção; o seeder recusa correr em `production` e avisa se as imagens faltarem.
- Ids com prefixo `DEMO-`: reexecutar substitui-os; o primeiro `casafari:sync` real desativa-os automaticamente (não vêm no feed).
- Fixture antiga (ids 1001–1003) removida da base local; `AGENCY_HERO_IMAGE` local a apontar para o hero de demo.
- Verificado com screenshots: homepage com hero real e destaques, `/comprar` com 9 cartões e filtros povoados (13 características), ficha completa com galeria, consultora e semelhantes.

---

## Adiantamentos sem dependências externas

$${\color{#6B7248}\textsf{2026-08-19 · 09:52}}$$

**Commit:** `1ea4c60` — `Adiantamentos sem dependências: leads:retry, zones:import, favicon/OG da marca, página 500 útil e testes de acessibilidade`

(Trabalho da secção B do [docs/CHECKLIST.md](docs/CHECKLIST.md) — nada disto precisava do feed, das credenciais ou do alojamento.)

### Comandos de manutenção
- `app/Console/Commands/LeadsRetry.php` — `leads:retry {--pending} {--id=*} {--dry-run}`:
  recoloca na queue as leads `failed` (por defeito), as `pending` paradas há mais de 1 h
  (`--pending` — caso "criadas antes de haver token"), ou ids específicos. Volta o estado
  a `pending`, limpa `last_error`, e avisa se `CASAFARI_TOKEN` continuar vazio. O job é
  idempotente, portanto repetir o comando é seguro.
- `app/Console/Commands/ZonesImport.php` — `zones:import {--path=} {--prune}`: carrega o
  conteúdo editorial das páginas de zona a partir de `database/content/zones/*.md` com
  front matter simples (`city_slug`, `locality_slug`, `title`, `meta_description`,
  `cover_url`, `published`); 1.º parágrafo = intro, resto = corpo. Upsert por
  (`city_slug`,`locality_slug`) — reimportar é seguro; `--prune` despublica zonas sem
  ficheiro; invalida a cache no fim. Exemplo em `_exemplo.md.dist` + README na pasta.

### Identidade
- Favicons e imagem Open Graph **gerados com a marca** (script GD com a Fraunces TTF,
  guardada fora do git): `favicon.ico` (contentor ICO com PNG 32), `favicon-32.png`,
  `favicon-192.png`, `apple-touch-icon.png` — "M." areia sobre azeitona; e
  `public/images/og-default.jpg` (1200×630: wordmark "Multifuturo." com o ponto azeitona,
  filete e claim sobre areia) — substitui o placeholder cru da Fase 1. Ligados no layout
  (`rel=icon` 32/192 + `apple-touch-icon`). Continuam a ser genéricos de marca — um
  logótipo oficial substitui-os quando existir.

### Página 500
- `resources/views/errors/500.blade.php` — saídas úteis (Tentar novamente, Início,
  Contactos) e contactos diretos da agência; sem Livewire nem BD (para não depender do
  que possa estar avariado). Strings novas em `lang/pt/ui.php`.

### Acessibilidade
- `tests/Feature/AccessibilityTest.php` — 5 testes sobre 8 páginas representativas:
  `lang="pt-PT"`, skip link + `<main id>`, **exatamente um `h1`**, landmarks e nav com
  `aria-label`; **todas as `<img>` com `alt`**; campos de formulário com label
  associada/aria-label; ícones com nome acessível; sem `autofocus` nem tabindex positivo.
  Não substituem auditoria manual (contraste, teclado, leitores de ecrã).
- `resources/views/components/site/consent-banner.blade.php` — `aria-label` nas duas
  checkboxes do banner (apanhado pelo teste novo).

### Testes
- `tests/Feature/MaintenanceCommandsTest.php` — 8 testes (retry: failed/pending/ids/
  dry-run/vazio; import: parse completo, upsert, published:false, --prune + erro sem
  city_slug, página de zona mostra o conteúdo importado).
- Total: **117 testes a passar**, 1 ignorado fora de produção. Pint limpo.

---

## Fase 7 — revisão de cobertura e CI

$${\color{#6B7248}\textsf{2026-08-18 · 15:49}}$$

**Commit:** `3db625a` — `Fase 7: revisão de cobertura — testes do mapper isolado (XXE, URLs, datas, idioma como elemento, limites), casos-limite (itens maus não param o sync, 300 imóveis, XSS do feed escapado, JSON-LD, casafari:inspect, leads JSON, filtros, zonas com acentos) e CI GitHub Actions`

### Checklist do brief (onde está coberta)
- sync cria, atualiza e desativa — `CasafariSyncTest` (Fase 3)
- feed vazio aborta sem desativar — `CasafariSyncTest` (Fase 3)
- hash inalterado salta escrita — `CasafariSyncTest` (Fase 3)
- Owner nunca persistido — `CasafariSyncTest` (Fase 3) + `PropertyMapperTest` (Fase 7)
- slug não muda quando o título muda — `CasafariSyncTest` (Fase 3)
- `gmap_visible=false` sem coordenadas no HTML nem no JSON-LD — `FrontendTest` (Fase 4)
- lead grava local mesmo com o CRM em baixo — `LeadsTest` (Fase 5)
- job marca falha com `status=false` em HTTP 200 — `LeadsTest` (Fase 5)
- AMI vazio falha em produção — `AgencyConfigTest` (Fase 1)
- fixture XML real anonimizada — **pendente do feed** (a atual é provisória)

### Código
- `app/Services/Casafari/PropertyMapper.php` — imóvel sem finalidade reconhecida passa a
  ser rejeitado no mapper (`InvalidArgumentException`) **antes** de tocar na BD; o sync
  conta o erro e continua com os restantes (antes rebentava na inserção).

### Testes novos
- `tests/Feature/PropertyMapperTest.php` — 9 testes: decimais com vírgula, booleanos
  variados, datas com fuso normalizadas; URLs não-http(s)/`javascript:`/`data:` rejeitados
  e datas inválidas → null; finalidade desconhecida rejeitada; sem `internal_id`/XML
  inválido lança; **idioma como elemento irmão** (config `lang_mode=element`); URL da foto
  em sub-elemento; **Owner removido mesmo com mapeamento a apontar para dentro dele**;
  **XXE/DOCTYPE não resolvidos** (`LIBXML_NONET`); limites de tamanho das strings.
- `tests/Feature/EdgeCasesTest.php` — 11 testes: imóvel sem finalidade conta como erro e os
  outros são criados (exit code FAILURE); feed com **300 imóveis** sincroniza, pagina e
  entra no sitemap; **HTML vindo do CRM é escapado** em título/descrição/características/
  broker, na ficha e no cartão; JSON-LD escapa `</script>`; `casafari:inspect` descreve a
  fixture e falha com mensagem clara sem URL/ficheiro; leads em JSON (201 / 422 com erros
  por campo); filtros por tipo, área e freguesia + freguesia limpa ao mudar de concelho;
  características do URL limitadas a 12 e normalizadas; zonas com acentos → slugs ASCII e
  freguesia inexistente → 404.
- Total: **104 testes a passar**, 1 ignorado fora de produção. Pint limpo.

### CI
- `.github/workflows/tests.yml` — em cada push/PR: PHP 8.3 (`pdo_pgsql`, `redis`, `gd`…),
  PostgreSQL 16 e Redis como serviços, `composer install`, `npm ci && npm run build`,
  **Pint `--test`** e **Pest `--ci`** contra a base `testing`. Em falha, as linhas
  relevantes do log saem como *annotations* (visíveis sem login).

### Correções de CI (commits seguintes)
- `df75d35` (15:51) — o `.env` passa a ser criado **antes** do `composer install`: o
  `package:discover` arranca a aplicação e, sem `APP_ENV`, ela assume produção e **recusa
  arrancar sem AMI** (comportamento intencional). Documentado no README para máquinas novas.
- `535ba3a` (15:54), `0233dc9` (15:56) — Pest a publicar erros e início do log como annotations.
- `a0df88b` (15:59) — `phpunit.xml` sem a suite `Unit`: a pasta `tests/Unit` estava vazia e
  o git não a versiona, pelo que no runner não existia. **CI verde** a partir daqui.

---

## Fase 6 — legal e conformidade

$${\color{#6B7248}\textsf{2026-08-18 · 15:44}}$$

**Commit:** `2904ccf` — `Fase 6: legal e conformidade — políticas, página da agência, banner de cookies com consentimento granular, scripts bloqueados até opt-in`

### Textos legais e institucionais
- `lang/pt/legal.php` — **política de privacidade** (responsável, dados recolhidos por
  formulário e por favoritos/cookies, finalidades e fundamentos RGPD por alínea,
  destinatários incl. CASAFARI como subcontratante, prazos, direitos e CNPD, segurança,
  alterações; mostra a `privacy_policy_version` — a mesma gravada em cada lead),
  **termos e condições** (identificação com AMI, informação de imóveis não vinculativa,
  utilização, propriedade intelectual, responsabilidade, Livro de Reclamações e RAL, lei
  aplicável), **política de cookies** (necessários: sessão, XSRF, cookie de consentimento;
  localStorage dos favoritos; análise/marketing inexistentes e só após opt-in; conteúdos de
  terceiros — OpenStreetMap só ao clicar; como gerir) e **página "A agência"**. Textos com
  placeholders `:name`, `:ami`, `:address`, `:email`, `:phone` preenchidos pela config.
  Nota no ficheiro: minutas a rever por quem responde pela conformidade legal.
- `resources/views/pages/legal.blade.php` — documento genérico: cabeçalho, índice lateral
  (sticky), secções numeradas com âncoras. `pages/placeholder.blade.php` **removido**.
- `app/Http/Controllers/PageController.php` — `about/privacy/terms/cookies` servem
  `legal()` com as substituições; deixam de ter `noindex`.
- `app/Support/helpers.php` (+ `composer.json` autoload `files`) — `trans_replace()`.

### Consentimento de cookies (sem CMP de terceiros)
- `config/consent.php` — nome do cookie (`mf_consent`), validade (180 dias), versão,
  categorias opcionais (`analytics`, `marketing`).
- `resources/js/consent.js` — store Alpine `consent`: lê/escreve cookie first-party JSON
  (`SameSite=Lax`, `Secure` em HTTPS); `acceptAll()`, `rejectAll()`, `saveChoices()`,
  `manage()`; **ativa scripts `type="text/plain"[data-consent=…]` só após opt-in** da
  categoria e dispara `mf:consent`. Importado em `app.js`.
- `resources/views/components/site/consent-banner.blade.php` — banner fixo em baixo com
  **Aceitar tudo / Recusar não essenciais / Personalizar** (recusa com o mesmo peso visual),
  painel por categoria (necessários sempre ativos; análise; marketing) e "Guardar escolhas";
  ligação à política de cookies; `role=dialog`, `aria-labelledby/describedby`.
- `resources/views/components/consent-script.blade.php` — `<x-consent-script category
  src|slot>` renderiza `type="text/plain"`: o navegador nunca o executa antes do opt-in.
- `resources/views/components/layouts/app.blade.php` — `window.MF_CONSENT` (config, sem
  segredos), banner incluído, e **`@livewireStyles`/`@livewireScripts` forçados**: o
  Livewire 3 só injetava o seu script (que traz o Alpine) em páginas com componente
  Livewire — nas restantes não havia menu móvel, favoritos nem banner. Bug encontrado ao
  correr a app em headless.
- `resources/views/components/site/footer.blade.php` — ligação **"Gerir cookies"**
  (reabre o banner em modo personalizar).

### Testes
- `tests/Feature/LegalTest.php` — 9 testes: páginas legais/institucional respondem, são
  indexáveis, têm nome/AMI/email substituídos e **nenhum placeholder por substituir**;
  privacidade mostra a versão em vigor; cookies descreve o cookie de consentimento, os
  favoritos locais e o OpenStreetMap; rodapé liga a políticas, Livro de Reclamações e
  "Gerir cookies"; banner presente em todas as páginas com recusa e personalização;
  **nenhuma página carrega scripts externos** (nem GTM/GA/Facebook); `<x-consent-script>`
  renderiza como `text/plain`; `trans_replace()`.
- `tests/Feature/PublicPagesTest.php` — teste das páginas provisórias removido (já não existem).
- Total: **84 testes a passar**, 1 ignorado fora de produção. Pint limpo.

---

## Fase 4 — correções após run visual

$${\color{#6B7248}\textsf{2026-08-18 · 15:23}}$$

**Commit:** `832b775` — `Fase 4: correções após run visual — URLs locais seguem o host (sem hosts), fallback inline de imagens, hero bege quando não há fotografia`

- `app/Providers/AppServiceProvider.php` — em `local` os URLs absolutos deixam de ser forçados a `APP_URL` (seguem o host do pedido: `localhost` ou `multifuturo.test`); sem isto o CSS/JS não carregavam via `localhost` sem entrada no `hosts`. Em produção e testes continuam forçados.
- `resources/views/components/property/image.blade.php` e hero — fallback de imagem em `onerror` inline (o listener JS chegava tarde para imagens já falhadas).
- `resources/views/pages/home.blade.php` — sem fotografia, o hero é bege com texto escuro e botão azeitona (nunca um bloco grande de verde).
- `resources/views/pages/property.blade.php` — foto do consultor esconde-se se falhar.
- Verificado com screenshots headless (Edge) de `/`, `/comprar` e da ficha.

---

## Fase 4 — frontend público

$${\color{#6B7248}\textsf{2026-08-18 · 15:16}}$$

**Commit:** `771aaa8` — `Fase 4: frontend público — homepage, listagens Livewire com filtros, ficha de imóvel, zonas, favoritos, sitemap e cache`

**Referência de layout:** template Wix "Consultor Imobiliário (Elegante)" (wh-1112), indicado
pelo cliente. Seguiu-se a **estrutura e o tom** (hero full-bleed com texto centrado, secções
largas, três colunas de argumentos, formulário no fim, rodapé com políticas), com a nossa
paleta beje/azeitona e Fraunces + Inter. Sem depoimentos (não há depoimentos reais).

### Suporte
- `app/Support/Format.php` — `price()` ("785 000 €", "/mês" no arrendamento, "Preço sob
  consulta"), `area()` ("142 m²"), `typology()` ("T3"), `location()` ("Estoril, Cascais").
- `app/Support/PropertyCache.php` — cache com tag `properties` (TTL 1 h) para tudo o que lê
  imóveis; `remember()`/`flush()`; cai para cache sem tags em drivers sem suporte.
- `app/Listeners/FlushPropertyCache.php` — ouve `PropertiesSynced` e limpa a cache **só se**
  houve criados/atualizados/desativados (ou `--force`).
- `app/Support/Zones.php` — concelhos e freguesias derivados da carteira ativa (com
  contagens venda/arrendamento), slugs públicos, resolução slug → nome. Em cache.
- `resources/js/app.js` — store Alpine `favorites` (localStorage, só slugs) e fallback global
  de imagens (`data-fallback`) para o placeholder local quando um URL do CRM falha.
- `public/images/placeholder-property.jpg` — placeholder local gerado (GD).

### Componentes
- `resources/views/components/property/image.blade.php` — `<img>` do CRM com `loading=lazy`,
  `decoding=async`, largura/altura e `aspect-ratio` explícitos (sem layout shift), fallback.
- `resources/views/components/property/card.blade.php` — **cartão de imóvel** reutilizável:
  foto 4:3, badge "Exclusivo", botão de favorito (Alpine/localStorage, `aria-pressed`),
  referência + finalidade, título, localização, preço em Fraunces, specs (T, área, lote, CE).
- `resources/views/components/property/gallery.blade.php` — capa + 4 miniaturas; lightbox
  Alpine (Esc, ← →, foco preso, `x-trap.noscroll`); sem JS as imagens são links diretos.
- `resources/views/pagination/multifuturo.blade.php` — paginação com os tokens (funciona
  em Livewire e em páginas normais).
- `resources/views/components/site/header.blade.php` — + **Zonas** e **Favoritos** (com contador).

### Homepage
- `resources/views/pages/home.blade.php` — 1) hero full-bleed (foto de `AGENCY_HERO_IMAGE`
  ou capa do 1.º destaque; véu `ink/45`; título/lead centrados; CTA), 2) pesquisa rápida
  sobreposta, 3) **Imóveis em destaque** (grelha de cartões), 4) **Sobre a Multifuturo**
  (texto), 5) **Porquê a Multifuturo** (3 colunas, fundo `sand-100`), 6) **Zonas onde
  atuamos** (grelha de concelhos com contagem), 7) banda de contacto/avaliação em bege
  (sem áreas grandes de azeitona — o verde fica nos botões).
- `app/Http/Controllers/PageController.php` — `home()` com destaques (`is_featured`,
  completados com os mais recentes até 3–6), hero e zonas; `buy()`/`rent()` passam a
  montar o Livewire; descrições SEO por listagem.
- `config/agency.php` — `hero_image` (`AGENCY_HERO_IMAGE`).

### Listagens (`/comprar`, `/arrendar`)
- `app/Livewire/PropertyListing.php` — filtros **na query string** (`#[Url]`: `q`, `tipo`,
  `tipologia`, `concelho`, `freguesia`, `preco_min`, `preco_max`, `area_min`,
  `caracteristicas[]`, `ordenar`, `page`); primeiro render server-side já filtrado;
  **sanitização** de tudo o que vem do URL (limites, dígitos, whitelist de ordenação,
  máx. 12 características); pesquisa livre por referência/concelho/freguesia/zona/título;
  filtro de características pelo índice GIN; ordenação recentes/preço ↑/↓ (`NULLS LAST`);
  **paginação real 12/página**; resultados e opções de filtro em cache (`PropertyCache`);
  finalidade fixa por rota (não é filtro).
- `resources/views/livewire/property-listing.blade.php` — cabeçalho com contagem
  (`aria-live`), ordenação, filtros em coluna (desktop) / painel (mobile), formulário GET
  funcional sem JS (`<noscript>` aplicar), grelha de cartões, paginação.
- `resources/views/pages/listing.blade.php` — monta `<livewire:property-listing>`.

### Ficha de imóvel (`/imoveis/{slug}`)
- `app/Http/Controllers/PropertyController.php` — `show()`: imóvel **inativo → 410 Gone**
  com semelhantes e contacto (não 404); semelhantes = mesma finalidade, prioridade ao mesmo
  concelho e tipo (cache); **JSON-LD `RealEstateListing`** (nome, URL, identificador, data,
  imagens, oferta com preço/moeda e função venda/arrendamento, morada, `numberOfRooms`,
  `floorSize`, `provider`; `geo` **só se `gmap_visible`** — o acessor `coordinates` já
  devolve null).
- `resources/views/pages/property.blade.php` — meta title/description e OG image por imóvel,
  canonical, breadcrumb, galeria, cabeçalho (ref., finalidade, exclusivo, título,
  localização, preço, favorito, visita virtual/vídeo/planta), características em `<dl>`,
  descrição, comodidades, **mapa**: só com `gmap_visible`; iframe OpenStreetMap criado
  **apenas ao clicar** (aviso explícito; zero pedidos externos até lá; `<noscript>` link);
  sem `gmap_visible` mostra "localização exata mediante contacto"; consultor (nome/foto);
  formulário de lead pré-preenchido com a referência (sticky); semelhantes.
- `resources/views/pages/property-gone.blade.php` — página 410 (`noindex`).

### Zonas
- `database/migrations/2026_08_18_160000_create_zones_table.php` + `app/Models/Zone.php` —
  texto editorial opcional por zona (`city_slug`, `locality_slug`, `title`,
  `meta_description`, `intro`, `body`, `cover_url`, `is_published`).
- `app/Http/Controllers/ZoneController.php` — `/zonas` (concelhos com contagens),
  `/zonas/{concelho}` (editorial + freguesias + imóveis paginados), `/zonas/{concelho}/
  {freguesia}`; 404 se a zona não existir na carteira; ligações para comprar/arrendar
  filtrados nessa zona.
- `resources/views/pages/zones.blade.php`, `pages/zone.blade.php`.

### Favoritos
- `app/Http/Controllers/FavoritesController.php` + `resources/views/pages/favorites.blade.php`
  — sem registo: o browser lê os slugs do localStorage e recarrega com `?slugs=`; o servidor
  devolve só cartões de imóveis ativos (máx. 60, slugs validados); `noindex`.

### SEO
- `app/Http/Controllers/SitemapController.php` — home, listagens, zonas (concelhos e
  freguesias), avaliação, contactos e **todos os imóveis ativos** (`lastmod` = data do CRM),
  em chunks; em cache.
- `routes/web.php` — `property.show`, `zones.*`, `favorites`.
- `lang/pt/ui.php` — blocos `listing`, `property`, `favorites`, `zones`, `home_sections`
  (todo o texto da homepage é editável aqui).

### Testes
- `tests/Feature/FrontendTest.php` — 18 testes: homepage (destaques, zonas, banda);
  `/comprar` vs `/arrendar` e inativos excluídos; filtros lidos da query string no 1.º
  render; preço/características/limpar; ordenação; paginação 12 e sanitização de valores
  maliciosos; pesquisa livre; ficha (JSON-LD, canonical, OG, formulário pré-preenchido);
  **sem coordenadas no HTML nem JSON-LD com `gmap_visible=false`**; com `true` tem `geo` e
  o iframe só existe dentro de `<template x-if>`; **410 para inativos** com semelhantes;
  semelhantes nunca incluem o próprio nem outra finalidade; zonas derivadas e 404 para
  zona inexistente; editorial de zona; favoritos filtram inativos e lixo; sitemap só ativos
  + zonas; cache limpa no `PropertiesSynced` com alterações e intacta sem alterações.
- Total: **77 testes a passar**, 1 ignorado fora de produção. Pint limpo.

### Notas
- Falta a fotografia do hero (`AGENCY_HERO_IMAGE`) e um `og-default.jpg` real.
- Em local, as fotos dos 3 imóveis fictícios apontam para `example.test` e caem no placeholder.

---

## Fase 5 — leads

$${\color{#6B7248}\textsf{2026-08-18 · 14:56}}$$

**Commit:** `1868e10` — `Fase 5: leads — migration, model, StoreLeadRequest, LeadController, job SendLeadToCasafari, notificação, formulário e testes`

(A Fase 4 — frontend público — foi adiada por decisão do cliente até haver layout; a 5 e a 6 não dependem do visual.)

### Base de dados
- `database/migrations/2026_08_18_150000_create_leads_table.php` — tabela `leads`: `name`,
  `email`, `phone`, `message`; `property_id` (FK `properties`, `nullOnDelete`),
  `business_type`, `source` (`property|contact|valuation`), `payload` jsonb (campos extra
  da avaliação); RGPD: `consent_contact`, `consent_marketing` (ambos default **false**),
  `policy_version`, `ip_hash` (HMAC-SHA256 do IP com a APP_KEY — **nunca o IP em claro**),
  `user_agent`; CRM: `crm_status` (`pending|sent|failed`, default pending), `crm_response`
  jsonb, `sent_at`, `attempts`, `last_error`; índices `(crm_status, created_at)` e `email`;
  **CHECK constraints** em `crm_status` e `source`.
- `app/Enums/LeadStatus.php` (`Pending/Sent/Failed`), `app/Enums/LeadSource.php`
  (`Property/Contact/Valuation`).
- `app/Models/Lead.php` — casts (enums, jsonb, booleanos, datas), relação `property()`,
  `Lead::hashIp()`.
- `database/factories/LeadFactory.php` — só para testes.

### Fluxo HTTP
- `app/Http/Requests/StoreLeadRequest.php` — validação (`source` enum, `name` 2–120,
  `email` rfc, `phone` regex opcional, `message` ≤3000, `property_slug` existe,
  consentimentos booleanos, `payload` só com chaves conhecidas — `address/city/
  property_type/bedrooms/area/condition` — e limites); `prepareForValidation` normaliza
  os consentimentos; **anti-spam sem CAPTCHA**: `looksLikeSpam()` = honeypot `website`
  preenchido **ou** `form_ts` (timestamp assinado com HMAC da APP_KEY) inválido/forjado
  ou com menos de 3 s desde a renderização.
- `app/Http/Controllers/LeadController.php` — `POST /leads`: spam → aceita em silêncio
  (mesma resposta que um humano, sem gravar); caso contrário **grava a lead PRIMEIRO**
  (email em minúsculas, `policy_version` da config, `ip_hash`, `crm_status=pending`,
  imóvel resolvido pelo slug e `business_type` herdado) e só depois `SendLeadToCasafari::
  dispatch(...)->afterCommit()`. Responde com redirect + flash `lead_sent` ou JSON 201.
- `routes/web.php` — `POST /leads` (`leads.store`) com middleware `throttle:leads`.
- `app/Providers/AppServiceProvider.php` — `RateLimiter::for('leads')`: por IP (hash),
  **5/min e 20/h**.

### Job de envio ao CRM
- `app/Jobs/SendLeadToCasafari.php` (`ShouldQueue`) — `tries=5`, `backoff=[60,300,900,3600]`,
  timeout 60 s. Idempotente (não reenvia `sent`). Sem `CASAFARI_TOKEN` lança exceção
  (fica pending). `POST asForm` para `casafari.lead_url` com: `Token`, `PropertyID`
  (= `internal_id` do CRM, só quando há imóvel), `CustomerOriginID`, `EntityName`,
  `EntityEmail`, `EntityPhone`, `Message` (texto + contexto: origem, referência, dados de
  avaliação), `CreateProfile=true`, `EntityCulture=pt`, `EntityType` (config, **a confirmar**),
  `AssignBrokerIDFromProperty=true`, `IncludeOptIn`/`IncludeMailing` = consentimentos
  **tal como dados, nunca forçados**. **Armadilha tratada:** HTTP 200 com
  `json.status !== true` → grava `crm_response`/`last_error`, incrementa `attempts` e lança
  `RuntimeException` (entra em retry). Sucesso → `sent` + `sent_at` + `crm_response`.
  `failed()` → `failed`, `last_error`, `Log::error` e notificação.
- `app/Notifications/LeadDeliveryFailed.php` — email para `casafari.alert_email` com os
  dados necessários ao envio manual.

### Formulário e páginas
- `resources/views/components/lead-form.blade.php` — `<x-lead-form source="property|
  contact|valuation" :property>`: server-rendered, funciona sem JS; honeypot fora do
  ecrã e do tab; `form_ts` assinado; campos extra da avaliação; **duas checkboxes de
  consentimento separadas, desmarcadas**; aviso com link para a política de privacidade;
  mensagem pré-preenchida com a referência do imóvel; erros por campo; flash de sucesso.
  Visual mínimo com os tokens — a re-skinnar na Fase 4.
- `resources/views/pages/contact.blade.php` e `pages/valuation.blade.php` — páginas
  `/contactos` e `/quanto-vale-a-minha-casa` funcionais (deixam de ser provisórias/noindex).
- `app/Http/Controllers/PageController.php` — `contact()` e `valuation()` servem as novas views.
- `lang/pt/ui.php` — bloco `lead` (títulos, campos, consentimentos, aviso, sucesso, erro).

### Configuração
- `config/agency.php` — `privacy_policy_version` (`AGENCY_PRIVACY_POLICY_VERSION`, default
  2026-08-18) — atualizar quando o texto da política mudar.
- `config/casafari.php` — `lead_entity_type` (`CASAFARI_LEAD_ENTITY_TYPE`, default `Lead`,
  **a confirmar com a documentação**).
- `.env`/`.env.example` — as duas variáveis acima.

### Testes
- `tests/Feature/LeadsTest.php` — 17 testes: grava local + queue (email normalizado,
  consentimentos false, policy_version, ip_hash sem IP); **grava local mesmo com o CRM
  em baixo**; associação ao imóvel e finalidade herdada; consentimentos separados;
  payload da avaliação (chave desconhecida rejeitada); validação; **honeypot aceita em
  silêncio sem gravar**; timestamp rápido/forjado = spam; **rate limiting 429 ao 6.º**;
  job envia os campos esperados (PropertyID = internal_id, IncludeOptIn/IncludeMailing) e
  marca sent; sem PropertyID quando não há imóvel; **HTTP 200 com status=false lança
  exceção e fica pending**; `failed()` marca failed e notifica; idempotência; sem token não
  chama o CRM; tries/backoff; páginas mostram honeypot e consentimentos desmarcados.
- `tests/Feature/PublicPagesTest.php` — `valuation`/`contact` saem da lista de provisórias.
- Total: **59 testes a passar**, 1 ignorado fora de produção. Pint limpo.

---

## Fase 3 — motor de sincronização CASAFARI

$${\color{#6B7248}\textsf{2026-08-18 · 14:46}}$$

**Commit:** `3214eed` — `Fase 3: motor de sincronização CASAFARI (casafari:sync), mapper configurável, agendamento e testes`

### Serviços (`app/Services/Casafari/`)
- `FeedClient.php` — GET ao `feed_url` com `timeout` (180 s) e `retry(3, 5000)` da config,
  corpo lido **em streaming** para `storage/app/casafari/latest.xml` (escreve num `.part`
  e só substitui o `latest.xml` se o pedido correu bem; 0 bytes = erro). Sem URL lança exceção.
- `FeedReader.php` — `XMLReader` (`LIBXML_NONET`, sem entidades externas) que devolve, um de
  cada vez, o **XML bruto** de cada nó de imóvel (`feed.item_node`), saltando de irmão em
  irmão sem carregar o documento — é sobre esse texto que se calcula o hash.
- `PropertyMapper.php` — XML de um imóvel → array para `Property`. Toda a nomenclatura de
  nós vem de `config('casafari.mapping')` (caminhos `A/B`, `@attr`, `A/@attr`); o ficheiro
  não conhece nomes de elementos. **Owner é removido do DOM antes de qualquer leitura**
  (`feed.ignored_nodes`), com comentário a explicar o porquê (RGPD/minimização; não há
  coluna). Broker: só nome e foto. Tudo tratado como não confiável: tipos forçados,
  strings limitadas, URLs validados (`http(s)` apenas), decimais com vírgula aceites,
  booleanos por lista `truthy`, finalidade normalizada por `business_type_map`
  (`sale/venda/…`, `rent/arrendamento/…`), traduções por idioma (atributo `lang` ou
  elemento), fotos ordenadas por `order` com URLs inválidos descartados, características
  em minúsculas e sem duplicados, datas do CRM normalizadas para o fuso da aplicação.
- `PropertySyncer.php` — motor: carrega `internal_id → payload_hash` conhecidos numa query;
  por imóvel: `sha256` do nó; se igual e sem `--force` só toca em `synced_at`/`is_active`
  via `toBase()` (não mexe no `updated_at`); senão `fill+save` no existente (**slug nunca
  reescrito**) ou `create` com `generateSlug()`. `internal_id` duplicado no feed conta como
  erro (primeira ocorrência ganha). **Guard crítico:** `seen < casafari.min_items` (mín. 1)
  lança `EmptyFeedException` **antes** de qualquer desativação. Desativação por semântica
  de conjunto — `is_active=false` onde `internal_id NOT IN (vistos)`; acima de 30 000 ids
  usa tabela temporária em vez de bindings. Se houve erros de mapeamento a desativação é
  **saltada** (não sabemos que imóveis falharam). Erros por imóvel não param a execução;
  são registados e contados.
- `SyncResult.php` — contadores (`seen/created/updated/skipped/deactivated/errors`),
  `deactivationSkipped`, mensagens de erro (máx. 20), duração; `toArray()` para logs.
- `EmptyFeedException.php`.

### Comando, evento e agendamento
- `app/Console/Commands/CasafariSync.php` — `casafari:sync {--force} {--dry-run} {--file=}`:
  lock em cache (`casafari:sync`, 1 h) contra execuções simultâneas; barra de progresso;
  tabela de resultados; `Log::info` com o resumo; `Log::error` em feed vazio/exceção;
  dispara `PropertiesSynced` (não em dry-run); **exit code FAILURE** com feed vazio,
  falha de download ou erros de mapeamento — é isso que faz o scheduler notificar.
- `app/Events/PropertiesSynced.php` — evento com o `SyncResult` (a Fase 4 ouve-o para
  invalidar a cache Redis das listagens).
- `routes/console.php` — `casafari:sync` `hourlyAt(7)`, `withoutOverlapping(120)`,
  `runInBackground()`, output anexado a `storage/logs/casafari-sync.log`, e
  `emailOutputOnFailure(CASAFARI_ALERT_EMAIL)` se definido. Removido o comando `inspire`.

### Configuração
- `config/casafari.php` — novos: `alert_email`, `min_items`, bloco `feed` (`item_node`,
  `lang_mode`, `lang_name`, `default_locale`, `ignored_nodes=[Owner]`) e bloco `mapping`
  (`fields`, `translations`, `photos`, `features`, `broker`, `business_type_map`, `truthy`)
  — **marcado como PROVISÓRIO** em comentário: palpite de trabalho até o `casafari:inspect`
  correr sobre o feed real; só este bloco muda nessa altura.
- `config/database.php` — ligação `pgsql` com `timezone` = `APP_TIMEZONE` (Europe/Lisbon):
  o Eloquent grava datas sem offset e o Postgres interpretava-as em UTC (bug apanhado pelos
  testes: `crm_updated_at` desviado uma hora).
- `.env`/`.env.example` — `CASAFARI_MIN_ITEMS=1`, `CASAFARI_ALERT_EMAIL=`.

### Testes
- `tests/Fixtures/casafari-feed.xml` — fixture **provisória** (estrutura segue a config;
  dados fictícios), 3 imóveis: venda com traduções pt/en, CDATA, fotos desordenadas + URL
  inválido, características duplicadas, Broker com email/telefone e **`<Owner>` de
  propósito**; arrendamento com `GmapVisible=0`; terreno com `BusinessType=Venda` e campos
  em falta.
- `tests/Feature/CasafariSyncTest.php` — 15 testes: criação com todos os campos mapeados
  (ordem das fotos, features normalizadas, broker só nome/foto, slug, hash); finalidade em
  português e `gmap_visible=0` (coordenadas ocultas); **Owner nunca persistido** (procura
  em todas as colunas de todas as linhas, incl. contactos do consultor); hash inalterado
  salta escrita (`updated_at` intacto, `synced_at` avança); alteração de título/preço/
  concelho atualiza **sem mudar o slug**; desaparecido do feed → `is_active=false` sem
  apagar, e reativa quando volta; **feed vazio → FAILURE sem desativar**; `min_items`
  (feed truncado) → FAILURE sem desativar; `--dry-run` não escreve nem dispara evento;
  `--force` reescreve; `PropertiesSynced` disparado; erros de mapeamento → não desativa;
  download com `Http::fake` grava `latest.xml`; HTTP 500 → FAILURE sem tocar na BD;
  sem `CASAFARI_FEED_URL` → FAILURE.
- Total: **44 testes a passar**, 1 ignorado fora de produção. Pint limpo.

### Documentação
- `README.md` — secção "Sincronização com o CASAFARI" (comandos, regras, aviso de
  estrutura provisória).

### Notas
- Corri `casafari:sync --file=tests/Fixtures/casafari-feed.xml` contra a base local:
  ficaram 3 imóveis fictícios em `multifuturo` (úteis para desenvolver a Fase 4;
  `migrate:fresh` limpa-os). Em produção a única fonte é o feed.
- Estratégia de fotos (Passo 0, item 2) continua pendente do volume real; o sync guarda
  os URLs do CRM em `photos` — hotlink por omissão, espelho local é acrescentável sem
  mexer no motor.

---

## Fase 2 — schema `properties`

$${\color{#6B7248}\textsf{2026-08-18 · 12:43}}$$

**Commit:** `da34971` — `Fase 2: migration properties, model Property, enum BusinessType, factory e testes de schema`

### Base de dados
- `database/migrations/2026_08_18_120000_create_properties_table.php` — tabela `properties`:
  - Identidade: `internal_id` (unique, chave do upsert), `reference` (index).
  - Negócio: `price decimal(12,2)`, `currency char(3)` default EUR, `business_type`
    (`sale|rent`), `property_type` (index), `property_condition`.
  - Divisões/áreas: `bedrooms`, `bathrooms`, `house_area`, `plot_area`, `gross_area`.
  - Localização: `country` default PT, `district` (index), `city` (concelho), `locality`
    (freguesia), `zone`, `zipcode`, `lat/lon decimal(10,7)`, `gmap_visible` default **false**.
  - Edifício: `floor_number`, `build_year`, `energy_rating`.
  - Ligações: `crm_property_url`, `video_url`, `virtual_tour_url`, `floorplan_url`.
  - jsonb: `translations` (`{ "pt": { title, description } }`), `photos`, `features`, `broker`
    (só nome e foto — **sem contactos**).
  - Publicação/sync: `slug` (unique, estável), `payload_hash char(64)`, `crm_updated_at`,
    `is_active` default true, `is_exclusive`, `is_featured`, `synced_at`, timestamps (tz).
  - Índices: `(is_active, business_type, price)`, `(city, locality)`,
    `(is_active, crm_updated_at)`, **GIN** em `features` (`jsonb_path_ops`), unique em
    `slug` e `internal_id`.
  - Comentário no topo com a regra de privacidade: **não existe coluna para `Owner`**;
    nunca se apagam linhas (só `is_active = false`).

### Aplicação
- `app/Models/Property.php` — casts (enum, decimais, booleanos, jsonb→array, datas),
  `getRouteKeyName()` = `slug`, scopes `active()`, `forSale()`, `forRent()`, `featured()`,
  `withFeatures([...])` (usa `@>` e o índice GIN), acessores `title`/`description`
  (com fallback de idioma), `coordinates` (**null se `gmap_visible` for false** — a
  regra de exposição vive no model, não em cada view), `coverPhoto`; `generateSlug()`
  a partir de tipo + concelho + referência, com sufixo numérico em colisão, chamado
  uma única vez na criação.
- `app/Enums/BusinessType.php` — `Sale`/`Rent` com `routeName()` (buy/rent),
  `label()` e `fromRouteName()`.
- `database/factories/PropertyFactory.php` — factory **só para testes** (estados
  `forRent`, `inactive`, `withoutMap`, `featured`); comentário a proibir o seu uso como seed.
- `lang/pt/ui.php` — `business.sale` = Venda, `business.rent` = Arrendamento.
- `sail.ps1` — comando `pint`.

### Testes
- `tests/Pest.php` — `RefreshDatabase` em todos os Feature (base `testing` em PostgreSQL;
  o schema usa jsonb/GIN e não se testa em SQLite).
- `tests/Feature/PropertySchemaTest.php` — 11 testes: colunas presentes; **nenhuma coluna
  `owner*`**; tipos jsonb e índices (GIN, compostos, unique); duplicados de `internal_id`
  e `slug` rejeitados; casts; filtro por características; scopes; **coordenadas ocultas
  com `gmap_visible=false`** (o dado existe na BD, não é exposto); slugs únicos e não
  recalculados; rota por slug; driver pgsql.
- `tests/Feature/SeoFilesTest.php` — usa `AppUrl::forceFromConfig()` (limpeza do Pint).
- Total: **29 testes a passar**, 1 ignorado fora de produção. Pint limpo.

### Notas
- Os nomes das colunas seguem o brief; quando o `casafari:inspect` correr sobre o feed
  real, as diferenças (nomes, tipos, campos em falta/extra) são reportadas antes de
  ajustar — não se força o feed a caber no schema.

---

## Changelog — data e hora em destaque

$${\color{#6B7248}\textsf{2026-08-18 · 12:36}}$$

**Commit:** `119852e` — `Changelog: data e hora de cada commit em destaque (verde azeitona)`

- `CHANGELOG.md` — reorganizado por commit; cada entrada abre com data e hora do commit
  em verde azeitona (`\color` numa expressão matemática, a única cor que o GitHub renderiza
  em Markdown), seguida do hash e do título.

---

## Fase 1 — scaffold e fundações

$${\color{#6B7248}\textsf{2026-08-18 · 12:32}}$$

**Commit:** `702773f` — `Fase 1: layout base, tokens Tailwind, fontes locais, sitemap/robots, 404, testes`

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

## Passo 0 — ambiente, configuração e `casafari:inspect`

$${\color{#6B7248}\textsf{2026-08-18 · 12:21}}$$

**Commit:** `717561f` — `Passo 0: ambiente Sail, configuração CASAFARI/agência e comando casafari:inspect`
(sobre o `8bfdfd1 Initial commit` de 2026-08-18 · 12:19 já existente no GitHub, cujo README de uma linha foi substituído)

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
