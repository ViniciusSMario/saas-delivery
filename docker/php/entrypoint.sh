#!/bin/sh
# Garante que o php-fpm (www-data) consiga escrever em storage/ e bootstrap/cache
# quando o código vem de um bind mount do host.
set -e
if [ -d storage ]; then
    chmod -R ugo+rwX storage bootstrap/cache 2>/dev/null || true
fi
exec docker-php-entrypoint "$@"
