<?php

namespace App\Platform\Console;

use App\Platform\Models\PlatformUser;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;

class PlatformCreateUser extends Command
{
    protected $signature = 'platform:create-user {email} {name} {--role=admin : admin ou support}';

    protected $description = 'Cria um usuário da equipe do SaaS (o 2FA é configurado no primeiro login)';

    public function handle(): int
    {
        $email = mb_strtolower($this->argument('email'));

        if (PlatformUser::where('email', $email)->exists()) {
            $this->components->error('Já existe um usuário com esse e-mail.');

            return self::FAILURE;
        }

        if (! in_array($this->option('role'), [PlatformUser::ROLE_ADMIN, PlatformUser::ROLE_SUPPORT], true)) {
            $this->components->error('Perfil inválido: use admin ou support.');

            return self::FAILURE;
        }

        $generated = null;
        $password = $this->input->isInteractive()
            ? password('Senha (mínimo 12 caracteres, vazio para gerar uma)')
            : '';

        if ($password === '') {
            $password = $generated = Str::password(16, symbols: false);
        } elseif (mb_strlen($password) < 12) {
            $this->components->error('A senha precisa ter pelo menos 12 caracteres.');

            return self::FAILURE;
        }

        PlatformUser::create([
            'name' => $this->argument('name'),
            'email' => $email,
            'password' => $password,
            'role' => $this->option('role'),
        ]);

        $this->components->info("Usuário {$email} criado. Entre em http://".config('tenancy.platform_domain').':'.(parse_url(config('app.url'), PHP_URL_PORT) ?: 80).' e configure o 2FA.');

        if ($generated) {
            $this->components->warn("Senha gerada (aparece só agora): {$generated}");
        }

        return self::SUCCESS;
    }
}
