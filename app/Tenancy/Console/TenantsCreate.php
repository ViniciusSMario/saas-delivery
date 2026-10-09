<?php

namespace App\Tenancy\Console;

use App\Tenancy\Actions\ProvisionTenant;
use Illuminate\Console\Command;
use Throwable;

class TenantsCreate extends Command
{
    protected $signature = 'tenants:create
        {slug : identificador público (vira <slug>.<TENANCY_BASE_DOMAIN>)}
        {name : nome do estabelecimento}
        {--domain= : domínio no lugar do subdomínio padrão}
        {--document= : CNPJ}';

    protected $description = 'Provisiona um novo estabelecimento (registro, schema, migrations e seed)';

    public function handle(ProvisionTenant $provision): int
    {
        try {
            $tenant = $provision->handle(
                $this->argument('slug'),
                $this->argument('name'),
                $this->option('domain'),
                $this->option('document'),
            );
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Tenant [{$tenant->slug}] ativo no schema [{$tenant->schema_name}].");
        $this->components->twoColumnDetail('Endereço', 'http://'.$tenant->primaryDomain().$this->port());

        return self::SUCCESS;
    }

    private function port(): string
    {
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);

        return $port ? ":{$port}" : '';
    }
}
