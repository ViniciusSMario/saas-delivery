#!/bin/sh
# Entrada dos containers de produção (app, fila, agendador).
# O cache de config/rotas/views é montado na subida: depende do .env do servidor.
set -e

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY não definida. Gere com: docker compose -f docker-compose.production.yml run --rm app php artisan key:generate --show" >&2
    exit 1
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    storage/app/tenants storage/app/backups storage/app/private

php artisan optimize --quiet

exec "$@"
