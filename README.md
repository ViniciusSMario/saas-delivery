# SaaS Delivery

SaaS multi-tenant para restaurantes: Laravel 12, Livewire 3, PostgreSQL 17 (um schema por estabelecimento), Redis e Docker.

Esta é a **Fase 1 (Fundação)**: camada de tenancy, comandos `tenants:*` e a suíte de isolamento. Os módulos de negócio chegam nas próximas fases.

## Subir o ambiente

```bash
cp .env.example .env
docker compose build
docker compose run --rm app composer install
docker compose run --rm -u root app php artisan key:generate
docker compose up -d
docker compose exec app php artisan central:migrate --seed
```

| Serviço | Endereço |
|---|---|
| App (site central) | http://localhost:8080 |
| Mailpit | http://localhost:8025 |
| Vite (dev) | http://localhost:5173 |
| PostgreSQL | localhost:5432 (usuário `saas` / `secret`, banco `saas`) |

`vendor/` e `node_modules/` ficam em volumes Docker (bind mount no Windows é lento demais para eles). Rode `composer` e `artisan` sempre dentro do container. Na primeira abertura, o Vite leva uns segundos para compilar.

## Criar um tenant de teste

```bash
docker compose exec app php artisan tenants:create empresa1 "Pizzaria Empresa 1"
```

Depois, abra **http://empresa1.localhost:8080**. Navegadores resolvem `*.localhost` para a sua máquina, então não precisa editar o `hosts`. O cardápio público fica em `http://empresa1.localhost:8080/cardapio` e em `http://localhost:8080/cardapio/empresa1`.

## Comandos

| Comando | O que faz |
|---|---|
| `central:migrate [--fresh] [--seed]` | migra o schema `public` (plataforma) |
| `tenants:create {slug} {nome} [--domain=] [--document=]` | provisiona: registro, schema, migrations, seed, ativo |
| `tenants:migrate [--tenant=slug] [--pretend]` | migra todos os tenants ativos e suspensos, ou um só |
| `tenants:rollback [--tenant=slug] [--step=1]` | desfaz migrations de tenant |
| `tenants:seed [--tenant=slug] [--class=]` | roda seeders de tenant |
| `tenants:status` | status, schema, versão e migrations pendentes de cada tenant |

Ordem do deploy: `central:migrate`, depois `tenants:migrate`. Se a migration falhar em um tenant, os outros seguem, a falha vai para `tenant_events` e o comando termina com erro.

## Testes

```bash
docker compose exec app php artisan test
```

Os testes rodam contra PostgreSQL e Redis reais (banco `saas_test`, Redis DB 14/15). A cada execução, o `public` é recriado e os tenants `teste1` e `teste2` são provisionados.

## Como a tenancy funciona

- **Conexões:** `central` sempre em `public`; `tenant` é a padrão e, sem tenant ativo, aponta para `tenant_none`, um schema que não existe. Assim, qualquer query sem tenant falha.
- **search_path do tenant:** `"<schema>,extensions"`, nunca `public`. Só `App\Tenancy\SchemaManager` mexe nele (há um teste de arquitetura que garante isso).
- **Schema:** o nome é `tenant_<ulid>`, gerado pelo sistema e imutável. Ele vem sempre de `public.tenants`, nunca da URL, e é validado por `/^tenant_[a-z0-9]{6,40}$/`.
- **Requisição:** `IdentifyTenant` resolve o tenant pelo host (`tenant_domains`) ou pelo slug (`/cardapio/{slug}`). Depois, `EnsureTenantIsActive` bloqueia suspenso (403) e provisioning/excluído (404) e ativa o contexto. Só então a sessão inicia. `EnsureSessionBelongsToTenant` derruba sessões de outro tenant. No `terminate`, o estado fechado volta.
- **Contexto auxiliar:** o prefixo de cache vira `tenant:<id>:`, o disco `tenant` aponta para `storage/app/tenants/<id>` e os logs ganham `tenant_id`. A sessão usa um store próprio com prefixo fixo e fica isolada pelo cookie por host.
- **Jobs:** use o trait `App\Tenancy\Jobs\TenantAware`. O id do tenant vai no payload, e o worker reativa esse tenant e reseta no fim. Despachar sem tenant ativo é erro.
- **Livewire:** `/livewire/update` e o upload temporário passam pelo grupo de middleware `tenant`.
- **Role do PostgreSQL por tenant:** está preparado (`config('tenancy.database_roles')`, ponto marcado em `SchemaManager::switchTo`), mas não ativado.

Estrutura: `app/Tenancy` (camada de tenancy), `app/Platform` (models da plataforma), `app/Modules` (negócio; não pode usar `Platform`), `database/migrations/{central,tenant}`.
