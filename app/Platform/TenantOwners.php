<?php

namespace App\Platform;

use App\Modules\Access\DefaultRoles;
use App\Modules\Access\Models\User;
use App\Platform\Notifications\TenantNotice;
use App\Tenancy\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Contato com os donos de um estabelecimento (lidos do schema dele). */
class TenantOwners
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    /** @return list<string> */
    public function emails(Tenant $tenant): array
    {
        try {
            $emails = $this->context->run($tenant, fn () => User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('slug', DefaultRoles::OWNER))
                ->pluck('email')
                ->all());
        } catch (Throwable) {
            $emails = [];
        }

        return array_values(array_unique(array_filter([$tenant->billing_email, ...$emails])));
    }

    public function notify(Tenant $tenant, TenantNotice $notice): void
    {
        foreach ($this->emails($tenant) as $email) {
            Notification::route('mail', $email)->notify($notice);
        }
    }
}
