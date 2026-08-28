# Pôr o site em produção

Guia para instalar e manter o site e o backoffice da Multifuturo Propriedades
num servidor próprio, com Docker — a mesma base que corre em desenvolvimento,
sem surpresas. Do zero até ao site no ar são cerca de **uma hora**, a maior
parte à espera de construções e do DNS.

O que é preciso ter antes de começar está na secção 1. Se ainda faltar algum
dado (AMI, SMTP), a secção 3 diz o que acontece sem ele.

---

## 1. O que é preciso

| | Recomendação |
|---|---|
| **Servidor (VPS)** | 2 vCPU · 4 GB RAM · 40 GB SSD · Ubuntu 24.04 LTS. Chega para a agência inteira; em Portugal e na Europa custa 8–15 €/mês (Hetzner, OVH, Scaleway, ou o alojador que a agência já tiver, desde que dê acesso root e permita Docker). |
| **Domínio** | Ex.: `multifuturo.pt`, com acesso ao painel de DNS. |
| **Email SMTP** | Servidor, porta, utilizador e palavra-passe de um email do domínio (`site@multifuturo.pt`). Sem isto os pedidos do site não chegam a ninguém. |
| **Licença AMI** | O número. **A aplicação recusa arrancar em produção sem ele** (é obrigatório por lei em toda a comunicação de mediação). |
| **Acesso** | SSH ao servidor (utilizador com `sudo`) e acesso ao repositório no GitHub. |

> O que **não** é preciso: painel de alojamento (cPanel/Plesk), nginx ou Apache
> instalados, PHP instalado. Tudo corre em contentores.

---

## 2. Preparar o servidor (uma vez)

Ligar por SSH e instalar o Docker (o guião oficial, um comando):

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker "$USER"   # sair e voltar a entrar para fazer efeito
docker --version && docker compose version
```

Firewall: só SSH, HTTP e HTTPS.

```bash
sudo ufw allow OpenSSH && sudo ufw allow 80/tcp && sudo ufw allow 443/tcp && sudo ufw --force enable
```

Clonar o projeto:

```bash
sudo mkdir -p /srv && sudo chown "$USER" /srv
cd /srv && git clone git@github.com:<organização>/<repositório>.git multifuturo
cd /srv/multifuturo
```

**DNS:** no painel do domínio, apontar `multifuturo.pt` (registo A) e
`www.multifuturo.pt` (A ou CNAME) para o IP do servidor. Fazer isto cedo —
pode levar de minutos a horas a propagar, e o certificado HTTPS só é emitido
quando o domínio já responder neste IP.

---

## 3. Configurar (`.env`)

```bash
cp .env.production.example .env
nano .env
```

Preencher o que está marcado **[OBRIGATÓRIO]**:

| Variável | O quê |
|---|---|
| `APP_KEY` | gerar: `docker compose -f compose.production.yaml run --rm --no-deps app php artisan key:generate --show` e colar |
| `APP_URL` | `https://multifuturo.pt` |
| `SITE_DOMAIN` | `multifuturo.pt` |
| `ACME_EMAIL` | email para avisos do certificado |
| `DB_PASSWORD` | `openssl rand -base64 32` |
| `MAIL_*` | os dados SMTP |
| `AGENCY_AMI` | o número da licença |
| `AGENCY_EMAIL`, `AGENCY_PHONE`, `AGENCY_ADDRESS` | contactos (aparecem no rodapé, contactos e textos legais) |

Sem AMI é possível arrancar num endereço de **pré-produção** para a agência
ver o site antes de abrir ao público: pôr `APP_ENV=staging` no `.env`. Nesse
modo o site funciona todo, mas o `robots.txt` bloqueia os motores de busca e o
rodapé mostra "Licença AMI: por atribuir". Ao mudar para `APP_ENV=production`
já tem de haver AMI.

> O `.env` **nunca** entra no Git (está no `.gitignore`). Guardar uma cópia
> num sítio seguro — é o que permite reconstruir o servidor.

---

## 4. Primeiro arranque

```bash
docker compose -f compose.production.yaml build          # ~5 min na primeira vez
docker compose -f compose.production.yaml up -d
docker compose -f compose.production.yaml exec app php artisan migrate --force
docker compose -f compose.production.yaml exec app php artisan make:filament-user
```

O último comando cria o **primeiro utilizador do backoffice** (nome, email,
palavra-passe). Depois, marcá-lo como administrador — é o administrador que
recebe os emails dos pedidos e gere os outros utilizadores:

```bash
docker compose -f compose.production.yaml exec app php artisan tinker --execute='App\Models\User::first()->forceFill(["is_admin" => true])->save(); echo "ok";'
```

Verificar:

```bash
docker compose -f compose.production.yaml ps            # seis serviços "running"/"healthy"
curl -I https://multifuturo.pt/up                        # HTTP/2 200
docker compose -f compose.production.yaml logs --tail 50 caddy   # certificado emitido
```

Depois, no browser:

1. `https://multifuturo.pt` — site (vazio de imóveis, é normal).
2. `https://multifuturo.pt/admin` — entrar com o utilizador criado.
3. **Valores de referência → Importar do INE** — enche o simulador de imediato
   (a partir daí atualiza-se sozinho à segunda-feira).
4. Criar a primeira ficha de imóvel, publicar, ver no site.
5. Fazer um pedido de informação no site e confirmar que o email chega à
   caixa do administrador. Se não chegar: secção 8.

---

## 5. Atualizar (cada vez que há código novo)

```bash
cd /srv/multifuturo && ./deploy/deploy.sh
```

O guião traz o código, **faz uma cópia de segurança antes de mexer**,
reconstrói as imagens, substitui os contentores, corre as migrações, reinicia
a fila e confirma que o site responde. Se algo falhar, pára nesse passo e o
que já estava a correr continua a correr. Demora 2–5 minutos; o site não fica
em baixo — os contentores novos substituem os antigos.

> **Porquê reiniciar a fila:** o worker que envia os emails é um processo de
> longa duração e não apanha código novo sozinho. O guião faz `queue:restart`
> por si; se fizer um deploy à mão, não se esquecer.

---

## 6. Cópias de segurança

- **Automáticas, todos os dias** à hora do `BACKUP_AT` (03:30): base de dados
  (`.sql.gz`) e ficheiros carregados (`.tar.gz`), guardadas `BACKUP_KEEP_DAYS`
  dias (14) no volume `backups`.
- **À mão**, antes de algo arriscado:
  `docker compose -f compose.production.yaml exec app php artisan backup:run`
- **Repor** uma cópia (base de dados e ficheiros, com confirmação):
  `./deploy/restore.sh --list` e depois `./deploy/restore.sh 2026-08-28_033000`

> **Uma cópia no próprio servidor não protege de um disco avariado nem de um
> servidor apagado.** Copiar as cópias para fora, uma vez por dia — o mais
> simples é um `rsync` a partir de outro computador, ou o serviço de snapshots
> do alojador (Hetzner, OVH e Scaleway têm, por 1–2 €/mês):
>
> ```bash
> # noutro computador, de madrugada (cron):
> rsync -az servidor:/var/lib/docker/volumes/multifuturo_backups/_data/ ~/backups-multifuturo/
> ```

---

## 7. Onde estão as coisas

| | |
|---|---|
| Código | `/srv/multifuturo` |
| Configuração | `/srv/multifuturo/.env` |
| Base de dados | volume Docker `multifuturo_pgsql` |
| Fotografias das fichas | volume `multifuturo_storage_public` |
| Documentos das fichas (privados) | volume `multifuturo_storage_private` |
| Cópias de segurança | volume `multifuturo_backups` (`/var/lib/docker/volumes/multifuturo_backups/_data`) |
| Certificados HTTPS | volume `multifuturo_caddy_data` (renovam-se sozinhos) |
| Logs | `docker compose -f compose.production.yaml logs -f app` (ou `caddy`, `queue`, `scheduler`) |

Os seis serviços: `caddy` (HTTPS e estáticos), `app` (a aplicação), `queue`
(emails), `scheduler` (cópias, INE, alertas), `pgsql`, `redis`. Todos arrancam
sozinhos com o servidor (`restart: unless-stopped`).

---

## 8. Se algo correr mal

| Sintoma | Ver |
|---|---|
| Site não abre / certificado inválido | O DNS já aponta para o servidor? `dig +short multifuturo.pt`. Portas 80 e 443 abertas na firewall? `docker compose -f compose.production.yaml logs caddy`. |
| "A aplicação recusa arrancar" | `AGENCY_AMI` vazio com `APP_ENV=production`. Preencher ou usar `staging`. |
| Erro 500 | `docker compose -f compose.production.yaml logs --tail 200 app`. Com `APP_DEBUG=false` o visitante vê uma página genérica; o erro está no log. |
| Pedidos chegam ao backoffice mas não há email | 1) `MAIL_*` certos? Testar: `docker compose -f compose.production.yaml exec app php artisan tinker --execute='Mail::raw("teste", fn($m) => $m->to("o-seu@email.pt")->subject("teste"));'` 2) A fila está a correr? `docker compose -f compose.production.yaml ps queue` 3) Trabalhos falhados: `exec app php artisan queue:failed` (e `queue:retry all` depois de corrigir). |
| Emails caem no spam | O remetente (`MAIL_FROM_ADDRESS`) tem de ser do domínio, e o domínio precisa de registos **SPF** e **DKIM** no DNS — o fornecedor de email indica quais. |
| Fotografias não aparecem | `docker compose -f compose.production.yaml exec app php artisan storage:link` e ver permissões do volume. |
| Mudei o `.env` e não fez efeito | As caches geram-se no arranque de cada contentor: `docker compose -f compose.production.yaml restart app queue scheduler`. |
| Espaço em disco | `docker system df`; limpar imagens antigas com `docker image prune -f` (o deploy já o faz). |
| Voltar à versão anterior | `git log --oneline -5`, `git checkout <commit>` e `./deploy/deploy.sh`; se houve migrações destrutivas, `./deploy/restore.sh` com a cópia feita antes do deploy. |

---

## 9. Testar tudo isto localmente (opcional)

A mesma configuração de produção corre no computador de desenvolvimento, para
validar antes de ter servidor — com um certificado interno e noutras portas,
para não colidir com o ambiente Sail:

```bash
cp .env.production.example .env.prodlocal
# no .env.prodlocal: SITE_DOMAIN=localhost, APP_URL=https://localhost:8443, HTTP_PORT=8080,
# HTTPS_PORT=8443, APP_ENV=staging, DB_PASSWORD=qualquer, APP_KEY gerada
export ENV_FILE=.env.prodlocal
alias dcp='docker compose -f compose.production.yaml -p multifuturo-prod --env-file .env.prodlocal'
dcp up -d --build
dcp exec app php artisan migrate --force
curl -k https://localhost:8443/up
dcp down -v   # limpar (apaga os volumes deste teste, não os do Sail)
```
