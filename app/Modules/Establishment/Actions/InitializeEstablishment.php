<?php

namespace App\Modules\Establishment\Actions;

use App\Modules\Access\Actions\SendSetPasswordLink;
use App\Modules\Access\Audit;
use App\Modules\Access\DefaultRoles;
use App\Modules\Access\Models\User;
use App\Modules\Establishment\Models\Establishment;

/**
 * Configuração inicial de um estabelecimento recém-provisionado: linha de
 * establishment e usuário dono, que recebe um e-mail para definir a senha.
 * Roda dentro do tenant, antes da ativação.
 */
class InitializeEstablishment
{
    public function __construct(private readonly SendSetPasswordLink $sendLink)
    {
    }

    public function handle(string $tradeName, ?string $document, ?string $ownerName, ?string $ownerEmail): ?User
    {
        Establishment::query()->firstOrCreate([], ['trade_name' => $tradeName, 'document' => $document]);

        if (! $ownerEmail) {
            return null;
        }

        $owner = User::create(['name' => $ownerName ?: $ownerEmail, 'email' => $ownerEmail]);
        $owner->roles()->attach(DefaultRoles::owner());

        Audit::record('user.created', $owner, ['nome' => $owner->name, 'email' => $owner->email, 'perfis' => ['Dono']]);

        $this->sendLink->handle($owner, invitation: true);

        return $owner;
    }
}
