# Changelog

Todas as alterações relevantes deste projeto são registadas aqui.
Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-PT/1.1.0/);
versionamento [SemVer](https://semver.org/lang/pt-BR/). Datas em ISO 8601.

## [Unreleased]

### Adicionado
- Esqueleto Laravel 12.66 criado dentro de Docker (sem Composer no Windows).
- Laravel Sail com PHP 8.3, PostgreSQL 16, Redis e Mailpit (`compose.yaml`).
  Portas no host: app 80, PostgreSQL 54320, Redis 63790, Mailpit 8025.
- Wrapper `sail.ps1` para PowerShell — o `./vendor/bin/sail` só corre em WSL2.
- `config/casafari.php`: `feed_url`, `lead_url`, `token`, `customer_origin_id`,
  timeout/retries do feed, pasta de amostras.
- `config/agency.php`: nome, AMI, contactos, morada, redes sociais, Livro de
  Reclamações eletrónico.
- `.env.example` com todas as variáveis (segredos vazios).
- Comando `casafari:inspect [--file=]`: descarrega o feed XML (ou lê um XML
  local), guarda `storage/app/casafari/sample.xml` e imprime hierarquia dos nós,
  estrutura completa do primeiro imóvel, contagem total, formato do `lang`
  (atributo vs. elemento) e número de fotos por imóvel. Só diagnóstico; não
  escreve na base de dados.
- Proposta tipográfica (Passo 0): escolhido **Fraunces + Inter**; alternativas
  avaliadas: Cormorant Garamond + Work Sans, Instrument Serif + Instrument Sans.
- README com arranque do ambiente e configuração.

### Alterado
- Locale da aplicação para `pt` (`APP_LOCALE`, `APP_FALLBACK_LOCALE`), faker
  `pt_PT`, timezone `Europe/Lisbon` (`APP_TIMEZONE`).
- Cache, sessão e queue passam a Redis (em vez de `database`).

### Pendente (bloqueia o Passo 0)
- `CASAFARI_FEED_URL` ainda não disponível: sem ele não há inspeção do feed real
  nem decisão sobre a estratégia de fotos (hotlink vs. espelho local).
- Entrada `127.0.0.1 multifuturo.test` no ficheiro `hosts` (requer Administrador).

## Histórico anterior à stack atual

- 2026-08-18 — Primeira abordagem em WordPress + DDEV (Fase 0 concluída) foi
  anulada por decisão do cliente; ambiente e ficheiros removidos. Projeto
  reiniciado em Laravel com os requisitos deste changelog.

[Unreleased]: ./
