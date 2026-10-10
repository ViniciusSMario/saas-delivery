# Imagens de produção. Um Dockerfile, dois alvos:
#   app: PHP-FPM com o código, vendor sem dev e os assets já compilados (app, fila, agendador)
#   web: Caddy com HTTPS automático, servindo public/ e repassando o PHP para o app
#
#   docker compose -f docker-compose.production.yml build

# ---------- dependências PHP (sem dev) ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---------- assets (Vite + Tailwind) ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
# O Tailwind lê as views de paginação em vendor/.
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
COPY --from=vendor /app/vendor/livewire/livewire/src/Features/SupportPagination/views ./vendor/livewire/livewire/src/Features/SupportPagination/views
RUN npm run build

# ---------- app (PHP-FPM) ----------
FROM php:8.3-fpm-alpine AS app

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql intl zip bcmath pcntl redis opcache gd \
    && apk add --no-cache postgresql17-client tar curl tini

COPY docker/production/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/production/fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN rm -rf tests docker node_modules .env .env.* .dev-credentials public/hot \
    && mkdir -p storage/app/private storage/app/public storage/app/tenants storage/app/backups \
       storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && php artisan package:discover --ansi

COPY docker/production/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

USER www-data
ENTRYPOINT ["/sbin/tini", "--", "app-entrypoint"]
CMD ["php-fpm"]

# ---------- web (Caddy) ----------
FROM caddy:2-alpine AS web
COPY --from=app /var/www/html/public /var/www/html/public
COPY docker/production/Caddyfile /etc/caddy/Caddyfile
