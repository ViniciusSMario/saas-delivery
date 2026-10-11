# Colocando em produção

Um servidor Linux com Docker roda tudo: app (PHP-FPM), fila, agendador, PostgreSQL, Redis e o Caddy (HTTPS automático). Este guia leva de um servidor vazio até o sistema no ar.

## 1. O que contratar

| Item | Sugestão | Custo aproximado |
|---|---|---|
| Servidor (VPS) | Hetzner CX22 / CPX21, DigitalOcean 2 GB, Contabo VPS S. Ubuntu 24.04, 2 vCPU, 4 GB de RAM, 40 GB de disco | R$ 30 a 60/mês |
| Domínio | Registro.br (`.com.br`) | R$ 40/ano |
| E-mail transacional | Amazon SES, Brevo, Resend ou Mailgun | grátis a poucos reais no começo |
| Backup fora do servidor | Backblaze B2, Cloudflare R2 ou Wasabi (compatíveis com S3) | centavos por GB |
| Erros (opcional) | sentry.io (plano grátis) ou GlitchTip | grátis |

## 2. DNS

No painel do domínio (ex.: Registro.br → DNS), aponte para o IP do servidor:

| Tipo | Nome | Valor |
|---|---|---|
| A | `@` (seudominio.com.br) | IP do servidor |
| A | `*` (todos os subdomínios: estabelecimentos e `admin`) | IP do servidor |
| A | `www` | IP do servidor |

Com o registro curinga (`*`), cada estabelecimento novo já funciona em `slug.seudominio.com.br`, e o Caddy emite o certificado na primeira visita (pergunta ao app se o domínio existe, em `/caddy/ask`).

## 3. Preparar o servidor

```bash
# como root
apt update && apt upgrade -y
curl -fsSL https://get.docker.com | sh
adduser deploy && usermod -aG docker deploy
ufw allow OpenSSH && ufw allow 80 && ufw allow 443 && ufw --force enable
```

Entre como `deploy` e traga o código (GitHub privado: crie uma "deploy key" de leitura):

```bash
sudo mkdir -p /opt/saas-delivery && sudo chown deploy: /opt/saas-delivery
git clone git@github.com:SUA-CONTA/saas-delivery.git /opt/saas-delivery
cd /opt/saas-delivery
```

## 4. Configurar

```bash
cp .env.production.example .env
nano .env    # preencha os campos <...>
```

- `APP_KEY`: gere com `docker compose -f docker-compose.production.yml run --rm --no-deps app php -r 'echo "base64:".base64_encode(random_bytes(32)), PHP_EOL;'`
- `CADDY_ASK_TOKEN`: `openssl rand -hex 32`
- Senhas do banco: `openssl rand -base64 24`
- Notificações no celular: `docker compose -f docker-compose.production.yml run --rm --no-deps app php artisan push:vapid` e copie as duas linhas para `VAPID_PUBLIC_KEY` e `VAPID_PRIVATE_KEY`; em `VAPID_SUBJECT`, `mailto:` com o e-mail de contato. Gere uma vez só: trocar depois obriga cada celular a ativar as notificações de novo.
- **Guarde o `.env` num gerenciador de senhas.** Sem a `APP_KEY`, as credenciais criptografadas (WhatsApp) e as sessões ficam inválidas.

## 5. Primeira subida

```bash
SKIP_PULL=1 ./scripts/deploy.sh
docker compose -f docker-compose.production.yml exec app php artisan platform:create-user voce@seudominio.com.br "Seu Nome"
```

Abra `https://admin.seudominio.com.br`, entre e configure o 2FA. Depois, `https://seudominio.com.br` mostra o site com os planos e o cadastro.

## 6. Atualizações

- **Automático:** cada push na `main` roda os testes no GitHub Actions; passando, ele entra no servidor por SSH e roda `./scripts/deploy.sh`. Configure em *Settings → Secrets and variables → Actions*: `DEPLOY_HOST`, `DEPLOY_USER` (deploy), `DEPLOY_SSH_KEY` (chave privada cuja pública está em `~deploy/.ssh/authorized_keys`) e, se mudar a pasta, `DEPLOY_PATH`.
- **Manual:** `cd /opt/saas-delivery && ./scripts/deploy.sh`.

O deploy coloca o sistema em manutenção por alguns segundos, roda as migrations da plataforma e de todos os estabelecimentos, sobe os containers novos e confere `/saude`.

## 7. Backups

- **Estabelecimentos:** backup diário às 03:00 (schema + fotos), com retenção. Com `BACKUPS_OFFSITE_DISK=s3` e as variáveis `AWS_*`, cada backup também vai para o bucket (fora do servidor).
- **Banco inteiro:** `platform:backup-database` roda às 02:30 (plataforma + todos os estabelecimentos, num arquivo só) e também vai para o bucket. É o que salva o dia se o servidor sumir.
- **Teste de restauração:** pelo menos uma vez por mês, restaure um backup num estabelecimento de teste (admin → estabelecimento → Restaurar).

### Recuperar tudo num servidor novo

1. Faça os passos 3 e 4 com o mesmo `.env` (mesma `APP_KEY`).
2. `docker compose -f docker-compose.production.yml up -d postgres redis`
3. Baixe o último `database-*.dump` do bucket e restaure:
   `docker compose -f docker-compose.production.yml exec -T postgres pg_restore -U postgres -d saas --clean --if-exists --no-owner --role=saas < database-AAAAMMDD.dump`
4. Restaure os arquivos dos estabelecimentos do bucket para o volume `storage` (pasta `app/tenants`).
5. `SKIP_PULL=1 ./scripts/deploy.sh`

## 8. Monitoramento

- **Disponibilidade:** cadastre `https://seudominio.com.br/saude` num monitor (UptimeRobot, Better Stack). Responde 200 com banco, Redis e disco em ordem; 503 se algo falhar.
- **Erros:** preencha `SENTRY_LARAVEL_DSN`. Cada erro chega com o estabelecimento (tenant) e o usuário.
- **Logs:** `docker compose -f docker-compose.production.yml logs -f app queue` ou em `storage/logs` (14 dias).

## 9. Comandos úteis

```bash
C="docker compose -f docker-compose.production.yml"
$C ps                                   # situação dos containers
$C logs -f --tail=100 app web queue     # logs
$C exec app php artisan tenants:status  # estabelecimentos e migrations
$C exec app php artisan tinker          # console
$C restart queue                        # reinicia a fila
```

## 10. Segurança

- O banco e o Redis não têm porta aberta: só os containers acessam.
- Com `TENANCY_DATABASE_ROLES=true`, cada estabelecimento usa um papel do PostgreSQL que só enxerga o próprio schema: mesmo um erro de código não lê dados de outro.
- A equipe de suporte entra num estabelecimento pelo admin ("Entrar como suporte"), com registro na auditoria do estabelecimento e da plataforma.
- Mantenha o servidor atualizado (`apt upgrade`) e o Docker com `restart: unless-stopped` (já configurado).
