# SaaS Delivery

SaaS multi-tenant para restaurantes: Laravel 12, Livewire 3, PostgreSQL 17 (um schema por estabelecimento), Redis e Docker.

Entregue até agora:

- **Fase 1 (Fundação):** camada de tenancy, comandos `tenants:*` e suíte de isolamento.
- **Fase 2 (Acesso e estabelecimento):** login, recuperação de senha, usuários, perfis e permissões, auditoria, dados do estabelecimento, horários e configurações. Admin da plataforma com 2FA, lista, criação, suspensão e reativação de estabelecimentos.

- **Fase 3 (Cardápio):** categorias, produtos com fotos e código de PDV, grupos de adicionais, combos e cardápio público (vitrine).

Próxima: pedidos e balcão (fase 4).

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
| Site central | http://localhost:8080 |
| Admin da plataforma | http://admin.localhost:8080 |
| Estabelecimento | http://&lt;slug&gt;.localhost:8080 |
| Mailpit (e-mails) | http://localhost:8025 |
| PostgreSQL (DBeaver etc.) | localhost:5433, usuário `saas`, senha `secret`, bancos `saas` e `saas_test` |

Use a pasta dentro do Ubuntu (WSL), com a integração WSL do Docker Desktop ligada. O `www-data` dos containers PHP usa o uid/gid 1000, o mesmo do seu usuário; se o seu for outro, rode `UID=$(id -u) GID=$(id -g) docker compose build`. `vendor/` e `node_modules/` ficam em volumes Docker. Rode `composer` e `artisan` sempre dentro do container. Navegadores resolvem `*.localhost` sozinhos, sem editar o `hosts`.

## Primeiro acesso

**Admin da plataforma** (equipe do SaaS):

```bash
docker compose exec app php artisan platform:create-user voce@exemplo.com "Seu Nome"
```

Entre em http://admin.localhost:8080. No primeiro login, escaneie o QR code com um app autenticador (Google Authenticator, Authy, 1Password...). O 2FA é obrigatório. Perfis: `admin` (gerencia estabelecimentos) e `support` (só consulta, `--role=support`).

**Estabelecimento com dono:** pelo admin (botão "Novo estabelecimento") ou pelo terminal:

```bash
docker compose exec app php artisan tenants:create pizzaria "Pizzaria Exemplo" --owner-email=dono@pizzaria.test --owner-name="Dono"
```

O dono recebe um e-mail com o link para definir a senha. Em desenvolvimento, o e-mail aparece no Mailpit (http://localhost:8025). Depois, ele entra em `http://pizzaria.localhost:8080/login`. Para um tenant que ainda não tem usuários:

```bash
docker compose exec app php artisan tenants:invite-user pizzaria outro@pizzaria.test "Nome" --role=dono
```

## Cardápio

- **Gestão** (permissão `menu.manage`, perfis Dono e Gerente): `/produtos`, `/categorias`, `/adicionais` e `/combos` no endereço do estabelecimento.
- **Vitrine pública:** `http://<slug>.localhost:8080/cardapio` ou `http://localhost:8080/cardapio/<slug>`. Mostra se está aberto agora, conforme o horário e o fuso do estabelecimento (turnos depois da meia-noite incluídos). Também tem destaques, combos, categorias, busca e detalhe do produto com adicionais. Pedido online chega na fase 4.
- **Dinheiro** sempre em centavos (`bigint`), com `App\Support\Money` e o cast `MoneyCast`. O formulário aceita "39,90", "1.234,56" ou "R$ 9,90".
- **Busca** por parte do nome usa índice trigram (`pg_trgm`, schema `extensions`); o código do produto é buscado por igualdade. O código é único entre os produtos não excluídos.
- **Produto excluído** é soft delete: sai do cardápio, mas pedidos antigos continuam apontando para ele. **Esgotado** mantém o produto visível, sem permitir pedido. Combo com item indisponível sai do cardápio.
- **Fotos** ficam em `storage/app/tenants/<id>/products/<produto>/` (até 5 por produto) e são servidas por id, nunca por caminho vindo da URL.
- **Cardápio de demonstração:** `docker compose exec app php artisan tenants:seed --tenant=empresa1 --class="Database\Seeders\Tenant\DemoMenuSeeder"`

## Comandos

| Comando | O que faz |
|---|---|
| `central:migrate [--fresh] [--seed]` | migra o schema `public` (plataforma) |
| `tenants:create {slug} {nome} [--owner-email=] [--owner-name=] [--domain=] [--document=]` | provisiona schema, migrations, seed, dados iniciais e dono |
| `tenants:invite-user {slug} {email} {nome} [--role=dono]` | cria usuário no estabelecimento e envia o convite |
| `tenants:migrate [--tenant=slug] [--pretend]` | migra todos os tenants ativos e suspensos, ou um só |
| `tenants:rollback [--tenant=slug] [--step=1]` | desfaz migrations de tenant |
| `tenants:seed [--tenant=slug] [--class=]` | roda seeders de tenant (idempotente) |
| `tenants:status` | status, schema, versão e migrations pendentes de cada tenant |
| `platform:create-user {email} {nome} [--role=admin]` | cria usuário da equipe do SaaS |

Ordem do deploy: `central:migrate`, `tenants:migrate`, `tenants:seed`. Se a migration falhar em um tenant, os outros seguem, a falha vai para `tenant_events` e o comando termina com erro.

## Testes

```bash
docker compose exec app php artisan test
```

Os testes rodam contra PostgreSQL e Redis reais (banco `saas_test`, Redis DB 14/15). A cada execução, o `public` é recriado e os tenants `teste1` e `teste2` são provisionados.

## Acesso e permissões

- **Usuários** vivem na tabela `users` do schema do tenant. O mesmo e-mail em dois estabelecimentos são contas independentes. Ninguém digita a senha de outra pessoa: usuário novo recebe um convite por e-mail.
- **Permissões** são o enum `App\Modules\Access\Permission`. **Perfis** são dados do tenant que agrupam permissões; os padrões são Dono, Gerente, Caixa, Garçom, Cozinha e Entregador, e o dono pode criar outros. A checagem usa `Gate::before`, então rotas usam `can:users.manage`, o Livewire usa `$this->authorize('users.manage')` e as views usam `@can`.
- **Contra escalada de privilégio:** só um dono concede o perfil de dono ou edita outro dono. Ninguém concede um perfil ou permissão que não tem. Sempre resta pelo menos um dono ativo.
- **Desativar** um usuário derruba a sessão dele na próxima requisição. Trocar a senha encerra as outras sessões (`AuthenticateSession`).
- **Login:** rate limit por tenant + e-mail + IP. Tokens de redefinição ficam no schema do tenant, e um token de A não vale em B.
- **Auditoria:** `audit_logs` registra login, logout, senhas, usuários, perfis, estabelecimento, horários e configurações, e aparece em `/auditoria`.
- **Admin da plataforma:** guard `platform` (tabela `public.platform_users`), só em `admin.localhost`, com 2FA TOTP obrigatório. No host de um tenant, o guard é sempre o `web`; no admin, sempre o `platform`.

## Como a tenancy funciona

- **Conexões:** `central` sempre em `public`; `tenant` é a padrão e, sem tenant ativo, aponta para `tenant_none`, um schema que não existe. Assim, qualquer query sem tenant falha.
- **search_path do tenant:** `"<schema>,extensions"`, nunca `public`. Só `App\Tenancy\SchemaManager` mexe nele (há um teste de arquitetura que garante isso).
- **Schema:** o nome é `tenant_<ulid>`, gerado pelo sistema e imutável. Ele vem sempre de `public.tenants`, nunca da URL, e é validado por `/^tenant_[a-z0-9]{6,40}$/`.
- **Requisição:** `IdentifyTenant` resolve o tenant pelo host (`tenant_domains`) ou pelo slug (`/cardapio/{slug}`). Depois, `EnsureTenantIsActive` bloqueia suspenso (403) e provisioning/excluído (404) e ativa o contexto. Só então a sessão inicia. `EnsureSessionBelongsToTenant` derruba sessões de outro tenant. No `terminate`, o estado fechado volta.
- **Contexto auxiliar:** o prefixo de cache vira `tenant:<id>:`, o disco `tenant` aponta para `storage/app/tenants/<id>` (logo do estabelecimento, uploads do Livewire) e os logs ganham `tenant_id`. A sessão usa um store próprio com prefixo fixo e fica isolada pelo cookie por host.
- **Jobs:** use o trait `App\Tenancy\Jobs\TenantAware`. O id do tenant vai no payload, e o worker reativa esse tenant e reseta no fim. Despachar sem tenant ativo é erro.
- **Livewire:** `/livewire/update`, upload e preview passam pelo grupo `tenant`. No `admin.localhost`, existe uma rota de update própria no grupo `platform`, registrada antes da do tenant.
- **Role do PostgreSQL por tenant:** está preparado (`config('tenancy.database_roles')`, ponto marcado em `SchemaManager::switchTo`), mas não ativado.

Estrutura: `app/Tenancy` (camada de tenancy), `app/Platform` (admin da plataforma, onboarding), `app/Modules/{Access,Establishment,Menu}` (negócio; não pode usar `Platform`), `app/Support` (Money), `app/Livewire/{Tenant,Platform}` (telas), `database/migrations/{central,tenant}`.
