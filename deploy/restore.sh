#!/usr/bin/env bash
#
# Repor uma cópia de segurança em produção. Correr NO SERVIDOR:
#
#     ./deploy/restore.sh 2026-08-28_033000
#
# (o nome da pasta em storage/backups — listar com ./deploy/restore.sh --list)
#
# Repõe a base de dados E os ficheiros carregados tal como estavam nessa cópia.
# O que foi feito depois dessa hora perde-se: pede confirmação explícita.
set -euo pipefail

cd "$(dirname "$0")/.."
COMPOSE="docker compose -f compose.production.yaml"

if [ "${1:-}" = "--list" ] || [ -z "${1:-}" ]; then
    echo "Cópias disponíveis:"
    $COMPOSE exec -T app sh -c 'ls -1 storage/backups'
    exit 0
fi

CARIMBO="$1"

if ! $COMPOSE exec -T app sh -c "test -d storage/backups/${CARIMBO}"; then
    echo "Não existe storage/backups/${CARIMBO}." >&2
    exit 1
fi

echo "Vai repor a base de dados e os ficheiros de ${CARIMBO}."
echo "Tudo o que foi feito depois dessa cópia PERDE-SE."
read -r -p "Escrever REPOR para continuar: " resposta
[ "$resposta" = "REPOR" ] || { echo "Cancelado."; exit 1; }

echo "→ Site em manutenção"
$COMPOSE exec -T app php artisan down --retry=60 || true
$COMPOSE stop queue scheduler >/dev/null

echo "→ Base de dados"
$COMPOSE exec -T app sh -c '
    set -e
    f=$(ls storage/backups/'"$CARIMBO"'/*.sql.gz | head -1)
    export PGPASSWORD="$DB_PASSWORD"
    psql -h "$DB_HOST" -U "$DB_USERNAME" -d postgres -q -c "DROP DATABASE IF EXISTS \"$DB_DATABASE\";"
    psql -h "$DB_HOST" -U "$DB_USERNAME" -d postgres -q -c "CREATE DATABASE \"$DB_DATABASE\";"
    gunzip -c "$f" | grep -v "^SET transaction_timeout" | psql -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" -q -v ON_ERROR_STOP=1
'

echo "→ Ficheiros carregados"
$COMPOSE exec -T app sh -c '
    set -e
    f=$(ls storage/backups/'"$CARIMBO"'/*.tar.gz | head -1)
    tar -xzf "$f" -C /var/www/html
'

echo "→ Caches e arranque"
$COMPOSE exec -T app php artisan optimize:clear >/dev/null
$COMPOSE exec -T app php artisan optimize >/dev/null
$COMPOSE start queue scheduler >/dev/null
$COMPOSE exec -T app php artisan up

echo "Reposto de ${CARIMBO}."
