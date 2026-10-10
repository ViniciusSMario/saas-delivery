#!/usr/bin/env bash
# Atualiza a produção: código novo, imagens, migrations (plataforma e todos os
# estabelecimentos) e containers. Roda no servidor, na pasta do projeto:
#   ./scripts/deploy.sh
# Também é chamado pelo GitHub Actions depois que os testes passam.
set -euo pipefail

cd "$(dirname "$0")/.."
COMPOSE="docker compose -f docker-compose.production.yml"

echo "==> Código"
if [ -d .git ] && [ "${SKIP_PULL:-0}" != "1" ]; then
    git pull --ff-only
fi

echo "==> Imagens"
$COMPOSE build --pull

echo "==> Banco e Redis"
$COMPOSE up -d postgres redis

echo "==> Modo manutenção"
$COMPOSE run --rm app php artisan down --retry=30 --render="errors::503" || true

echo "==> Migrations"
$COMPOSE run --rm app php artisan central:migrate --seed
$COMPOSE run --rm app php artisan tenants:migrate
$COMPOSE run --rm app php artisan tenants:seed

if grep -q '^TENANCY_DATABASE_ROLES=true' .env; then
    $COMPOSE run --rm app php artisan tenants:roles
fi

echo "==> Containers"
$COMPOSE up -d --remove-orphans
$COMPOSE exec -T app php artisan up

echo "==> Saúde"
for i in $(seq 1 30); do
    if $COMPOSE exec -T web wget -qO- http://127.0.0.1:8081/saude > /dev/null 2>&1; then
        echo "OK: aplicação no ar."
        docker image prune -f > /dev/null
        exit 0
    fi
    sleep 2
done

echo "ERRO: /saude não respondeu. Veja: $COMPOSE logs --tail=100 app web" >&2
exit 1
