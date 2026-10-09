<?php

namespace App\Platform\Console;

use App\Platform\Actions\OnboardTenant;
use App\Tenancy\TenantUrl;
use Illuminate\Console\Command;
use Throwable;

class TenantsCreate extends Command
{
    protected $signature = 'tenants:create
        {slug : identificador público (vira <slug>.<TENANCY_BASE_DOMAIN>)}
        {name : nome do estabelecimento}
        {--owner-email= : e-mail do dono, que recebe o link para definir a senha}
        {--owner-name= : nome do dono}
        {--domain= : domínio no lugar do subdomínio padrão}
        {--document= : CNPJ}';

    protected $description = 'Provisiona um estabelecimento (schema, migrations, seed, dados iniciais e dono)';

    public function handle(OnboardTenant $onboard): int
    {
        try {
            $tenant = $onboard->handle(
                slug: $this->argument('slug'),
                name: $this->argument('name'),
                document: $this->option('document'),
                ownerName: $this->option('owner-name'),
                ownerEmail: $this->option('owner-email'),
                domain: $this->option('domain'),
            );
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Tenant [{$tenant->slug}] ativo no schema [{$tenant->schema_name}].");
        $this->components->twoColumnDetail('Endereço', TenantUrl::to($tenant, 'login'));

        if ($email = $this->option('owner-email')) {
            $this->components->twoColumnDetail('Dono', "{$email} (link para definir a senha enviado por e-mail)");
        }

        return self::SUCCESS;
    }
}
