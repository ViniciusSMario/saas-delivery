<?php

namespace App\Livewire\Tenant;

use App\Modules\Access\Audit;
use App\Modules\Exports\Jobs\BuildDataExport;
use App\Modules\Exports\Models\DataExport;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

/** "Exportar meus dados": o dono baixa tudo do estabelecimento (LGPD / portabilidade). */
#[Title('Exportar dados')]
class DataExports extends Component
{
    public function request(): void
    {
        $this->authorize('billing.manage');

        if (DataExport::where('status', 'pending')->where('created_at', '>', now()->subMinutes(30))->exists()) {
            $this->addError('export', 'Já existe uma exportação sendo gerada. Aguarde terminar.');

            return;
        }

        $export = DataExport::create(['requested_by' => Auth::id()]);
        Audit::record('data.export_requested', $export);
        BuildDataExport::dispatch($export->id);
    }

    public function render()
    {
        $exports = DataExport::with('requester')->latest('id')->limit(10)->get();

        return view('livewire.tenant.data-exports', [
            'exports' => $exports,
            'generating' => $exports->contains('status', 'pending'),
        ]);
    }
}
