<?php

namespace App\Tenancy\Enums;

enum TenantStatus: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Suspended = 'suspended';
    case PendingDeletion = 'pending_deletion';
    case Deleted = 'deleted';
    case Failed = 'failed';
    case Restoring = 'restoring';

    public function label(): string
    {
        return match ($this) {
            self::Provisioning => 'Criando',
            self::Active => 'Ativo',
            self::Suspended => 'Suspenso',
            self::PendingDeletion => 'Exclusão agendada',
            self::Deleted => 'Excluído',
            self::Failed => 'Falhou',
            self::Restoring => 'Restaurando',
        };
    }

    /** Classe de selo (design system) para listas e detalhes. */
    public function badge(): string
    {
        return match ($this) {
            self::Active => 'badge-green',
            self::Suspended, self::Failed => 'badge-red',
            self::PendingDeletion, self::Restoring, self::Provisioning => 'badge-amber',
            self::Deleted => 'badge-gray',
        };
    }

    /** Status cujos schemas recebem migrations. */
    public static function migratable(): array
    {
        return [self::Active, self::Suspended];
    }
}
