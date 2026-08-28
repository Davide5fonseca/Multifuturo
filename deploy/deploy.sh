#!/usr/bin/env bash
#
# Atualização do site em produção. Correr NO SERVIDOR, na pasta do projeto:
#
#     ./deploy/deploy.sh
#
# O que faz, por ordem:
#   1. traz o código novo (git pull, só fast-forward)
#   2. cópia de segurança da base de dados e dos ficheiros (antes de mexer)
#   3. constrói as imagens novas
#   4. substitui os contentores (o arranque de cada um gera as caches)
#   5. corre as migrações
#   6. reinicia a fila — o worker é um processo de longa duração e não apanha
#      código nem rotas novas sozinho
#   7. verifica que o site responde
#
# Se algo falhar, pára aí (set -e) e o que já estava a correr continua a correr.
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE="docker compose -f compose.production.yaml"

if [ ! -f .env ]; then
    echo "Falta o .env (ver .env.production.example)." >&2
    exit 1
fi

SITE_DOMAIN="$(grep -E '^SITE_DOMAIN=' .env | cut -d= -f2- | tr -d '"' || true)"

echo "→ Código"
git pull --ff-only

echo "→ Cópia de segurança antes de mexer"
if $COMPOSE ps --status running app >/dev/null 2>&1 && [ -n "$($COMPOSE ps -q app)" ]; then
    $COMPOSE exec -T app php artisan backup:run
else
    echo "  (aplicação ainda não está a correr — primeira instalação, sem cópia)"
fi

echo "→ Imagens"
$COMPOSE build --pull

echo "→ Contentores"
$COMPOSE up -d --remove-orphans

echo "→ Migrações"
$COMPOSE exec -T app php artisan migrate --force

echo "→ Fila (queue:restart) e agendador"
$COMPOSE exec -T app php artisan queue:restart
$COMPOSE restart scheduler >/dev/null

echo "→ Limpeza de imagens antigas"
docker image prune -f >/dev/null

echo "→ Verificação"
if [ -n "$SITE_DOMAIN" ]; then
    for i in 1 2 3 4 5 6; do
        if curl -fsS --max-time 10 "https://${SITE_DOMAIN}/up" >/dev/null 2>&1; then
            echo "  https://${SITE_DOMAIN} responde. Deploy concluído."
            exit 0
        fi
        sleep 5
    done
    echo "  AVISO: https://${SITE_DOMAIN}/up não respondeu. Ver: $COMPOSE logs --tail 100 app caddy" >&2
    exit 1
fi

echo "Deploy concluído."
