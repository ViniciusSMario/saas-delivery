<?php

namespace App\Platform\Actions;

use App\Platform\Backups\TenantBackups;
use App\Platform\Notifications\TenantNotice;
use App\Platform\TenantOwners;
use App\Tenancy\Enums\TenantStatus;
use App\Tenancy\Models\Tenant;
use App\Tenancy\SchemaManager;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

/**
 * Exclusão com carência. Agendar bloqueia o acesso e avisa o dono; até a data
 * dá para cancelar. Depois dela, tenants:purge-deleted faz um backup final e
 * remove schema, arquivos e domínios.
 */
class TenantDeletion
{
    public function __construct(
        private readonly TenantBackups $backups,
        private readonly SchemaManager $schemas,
        private readonly TenantOwners $owners,
    ) {
    }

    public function schedule(Tenant $tenant, string $actor, ?string $reason = null): Tenant
    {
        if (! in_array($tenant->status, [TenantStatus::Active, TenantStatus::Suspended], true)) {
            throw ValidationException::withMessages(['status' => 'Só um estabelecimento ativo ou suspenso pode ser excluído.']);
        }

        $date = now()->addDays(config('platform.deletion_grace_days'));

        $tenant->update([
            'deletion_previous_status' => $tenant->status->value,
            'status' => TenantStatus::PendingDeletion,
            'deletion_scheduled_at' => $date,
        ]);
        $tenant->recordEvent('deletion_scheduled', array_filter(['date' => $date->toDateString(), 'reason' => $reason]), $actor);

        $this->owners->notify($tenant, new TenantNotice(
            "Exclusão agendada: {$tenant->name}",
            [
                "A conta de {$tenant->name} foi agendada para exclusão e o acesso está bloqueado.",
                'Os dados serão apagados definitivamente em '.$date->timezone('America/Sao_Paulo')->format('d/m/Y').'.',
                'Se foi um engano, fale com o suporte antes dessa data para cancelar.',
            ],
        ));

        return $tenant;
    }

    public function cancel(Tenant $tenant, string $actor): Tenant
    {
        if ($tenant->status !== TenantStatus::PendingDeletion) {
            throw ValidationException::withMessages(['status' => 'Este estabelecimento não está agendado para exclusão.']);
        }

        $tenant->update([
            'status' => TenantStatus::tryFrom((string) $tenant->deletion_previous_status) ?? TenantStatus::Active,
            'deletion_scheduled_at' => null,
            'deletion_previous_status' => null,
        ]);
        $tenant->recordEvent('deletion_cancelled', [], $actor);

        return $tenant;
    }

    /** Remoção definitiva (depois da carência). O backup final fica pelos dias de retenção. */
    public function purge(Tenant $tenant, string $actor = 'console'): void
    {
        if ($tenant->status !== TenantStatus::PendingDeletion) {
            throw ValidationException::withMessages(['status' => 'Só um estabelecimento agendado para exclusão pode ser removido.']);
        }

        $this->backups->create($tenant, 'final', $actor);

        $this->schemas->drop($tenant->schema_name);
        File::deleteDirectory(storage_path('app/tenants/'.$tenant->id));
        $tenant->domains()->delete();

        $tenant->update(['status' => TenantStatus::Deleted, 'deletion_scheduled_at' => null]);
        $tenant->recordEvent('deleted', [], $actor);
        $tenant->delete();
    }
}
