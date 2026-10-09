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

    /** Status cujos schemas recebem migrations. */
    public static function migratable(): array
    {
        return [self::Active, self::Suspended];
    }
}
