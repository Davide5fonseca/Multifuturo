# syntax=docker/dockerfile:1.7
#
# Imagem de produção da Multifuturo Propriedades.
#
# Etapas:
#   base    → PHP-FPM 8.3 com as extensões que o projeto usa
#   vendor  → dependências PHP (Composer, já com as extensões: o Filament
#             exige ext-intl para instalar)
#   assets  → CSS/JS com o Vite (Node 24). Precisa do vendor porque o tema do
#             backoffice importa CSS do Filament
#   app     → a aplicação: base + vendor + assets — imagem "app", usada pelos
#             serviços app, queue e scheduler
#   web     → Caddy com os ficheiros públicos — imagem "web": HTTPS
#             automático, ficheiros estáticos, encaminhamento do PHP
#
# Construir: docker compose -f compose.production.yaml build

# ---------------------------------------------------------------- base
FROM php:8.3-fpm-alpine AS base

# Extensões: PostgreSQL, Redis (phpredis), intl (datas e números em pt-PT),
# gd (imagens), zip/bcmath/exif/pcntl (Laravel, Filament, fila), opcache.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql redis intl gd zip bcmath exif pcntl opcache \
    # pg_dump e psql para as cópias de segurança (backup:run, restore.sh); tar para os ficheiros carregados.
    && apk add --no-cache postgresql16-client tar curl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/production/php.ini /usr/local/etc/php/conf.d/99-multifuturo.ini
COPY docker/production/www.conf /usr/local/etc/php-fpm.d/zz-multifuturo.conf

WORKDIR /var/www/html

# ---------------------------------------------------------------- vendor
FROM base AS vendor
# Camada em cache enquanto o composer.lock não muda.
# --no-scripts: o package:discover corre na etapa app, já com o código todo.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader \
    && composer clear-cache

# ---------------------------------------------------------------- assets
FROM node:24-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
# O tema do backoffice (resources/css/filament/admin/theme.css) importa CSS do Filament.
COPY --from=vendor /var/www/html/vendor/filament ./vendor/filament
RUN npm run build

# ---------------------------------------------------------------- app
FROM base AS app

COPY --chown=www-data:www-data --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /build/public/build ./public/build

# O que foi só para desenvolvimento não vai na imagem.
# APP_ENV=build: sem .env a aplicação assumiria "production" e recusaria
# arrancar sem AGENCY_AMI — na construção da imagem ainda não há .env.
RUN rm -rf tests docker/nginx docker/php-dev.ini .env .env.example node_modules storage/app/demo \
    && mkdir -p storage/app/public storage/app/private storage/backups storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && APP_ENV=build php artisan package:discover --ansi \
    && APP_ENV=build php artisan filament:assets --ansi \
    && chown -R www-data:www-data storage bootstrap/cache public

COPY --chmod=0755 docker/production/entrypoint.sh /usr/local/bin/entrypoint

USER www-data
EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);'

# ---------------------------------------------------------------- web
FROM caddy:2-alpine AS web
COPY docker/production/Caddyfile /etc/caddy/Caddyfile
# Só o que é público: o Caddy serve os estáticos e passa o resto ao PHP.
COPY --from=app /var/www/html/public /var/www/html/public
