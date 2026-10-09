<?php

namespace App\Livewire\Tenant;

use App\Modules\Access\Models\AuditLog;
use App\Modules\Establishment\Models\Setting;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Auditoria')]
class AuditLogIndex extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.tenant.audit-log-index', [
            'logs' => AuditLog::with('user')->latest('created_at')->latest('id')->paginate(30),
            'timezone' => Setting::read('timezone', 'America/Sao_Paulo'),
        ]);
    }
}
