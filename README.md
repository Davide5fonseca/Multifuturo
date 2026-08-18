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
