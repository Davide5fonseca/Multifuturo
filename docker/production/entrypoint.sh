#!/bin/sh
# Arranque de cada contentor da aplicação (app, queue, scheduler).
#
# Espera pela base de dados e pelo Redis, e prepara as caches do Laravel a
# partir do .env real. As caches fazem-se AQUI e não na construção da imagem
# porque dependem do ambiente (APP_URL, credenciais); e o OPcache não valida
# ficheiros (php.ini), por isso cada contentor novo tem de as gerar de fresco.
#
# As migrações NÃO correm aqui: são um passo explícito do deploy.sh, com
# cópia de segurança antes.
set -eu

cd /var/www/html

wait_for() {
    host="$1"; port="$2"; name="$3"; i=0
    until php -r 'exit(@fsockopen($argv[1], (int) $argv[2]) ? 0 : 1);' "$host" "$port" 2>/dev/null; do
        i=$((i + 1))
        if [ "$i" -ge 60 ]; then
            echo "entrypoint: $name ($host:$port) não respondeu em 60 s" >&2
            exit 1
        fi
        sleep 1
    done
}

wait_for "${DB_HOST:-pgsql}" "${DB_PORT:-5432}" "PostgreSQL"
wait_for "${REDIS_HOST:-redis}" "${REDIS_PORT:-6379}" "Redis"

# Ligação /public/storage → storage/app/public (fotografias das fichas).
php artisan storage:link --force >/dev/null 2>&1 || true

# Caches: config, rotas, vistas, eventos. Em segundos; poupam-se em cada pedido.
php artisan optimize >/dev/null

exec "$@"
