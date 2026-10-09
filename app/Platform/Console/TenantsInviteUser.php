<?php

namespace App\Platform\Console;

use App\Modules\Access\Actions\SaveUser;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\User;
use App\Tenancy\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/** Cria um usuário num tenant e envia o convite (útil para o primeiro dono de tenants antigos). */
class TenantsInviteUser extends Command
{
    protected $signature = 'tenants:invite-user {tenant : slug} {email} {name} {--role=dono : slug do perfil}';

    protected $description = 'Cria um usuário no estabelecimento e envia o e-mail para definir a senha';

    public function handle(TenantContext $context, SaveUser $save): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->first();

        if (! $tenant) {
            $this->components->error('Tenant não encontrado.');

            return self::FAILURE;
        }

        try {
            $context->run($tenant, function () use ($save) {
                $role = Role::where('slug', $this->option('role'))->firstOrFail();

                if (User::where('email', mb_strtolower($this->argument('email')))->exists()) {
                    throw new \RuntimeException('Já existe um usuário com esse e-mail neste estabelecimento.');
                }

                $save->handle(null, ['name' => $this->argument('name'), 'email' => $this->argument('email')], [$role->id]);
            });
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Usuário criado. O link para definir a senha foi enviado por e-mail (Mailpit em desenvolvimento).');

        return self::SUCCESS;
    }
}
