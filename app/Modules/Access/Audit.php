<?php

namespace App\Modules\Access;

use App\Modules\Access\Models\AuditLog;
use App\Modules\Access\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Registro de auditoria do tenant (tabela audit_logs no schema dele). */
class Audit
{
    public static function record(string $action, ?Model $subject = null, array $changes = [], ?User $actor = null): AuditLog
    {
        $actor ??= Auth::guard('web')->user();

        // Ação feita pela equipe do SaaS em modo suporte: fica marcado quem foi.
        $support = request()->hasSession() ? request()->session()->get('support_access') : null;

        if ($support && ! isset($changes['equipe'])) {
            $changes['via_suporte'] = $support['email'];
        }

        return AuditLog::create([
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $changes,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    /** Diferença legível entre valores antigos e novos, sem campos sensíveis. */
    public static function diff(Model $model): array
    {
        $hidden = ['password', 'remember_token', 'updated_at'];
        $changes = [];

        foreach ($model->getChanges() as $key => $new) {
            if (in_array($key, $hidden, true)) {
                continue;
            }

            $changes[$key] = ['de' => $model->getOriginal($key), 'para' => $new];
        }

        return $changes;
    }
}
