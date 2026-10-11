<?php

namespace App\Livewire\Tenant\Tables;

use App\Modules\Access\Audit;
use App\Modules\Establishment\Models\Establishment;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tables\TableOrdering;
use App\Support\QrCode;
use Livewire\Attributes\Title;
use Livewire\Component;

/** QR codes das mesas para imprimir e como o pedido pelo celular funciona. */
#[Title('QR codes das mesas')]
class QrCodes extends Component
{
    public string $mode = TableOrdering::DEFAULT_MODE;

    public bool $saved = false;

    public function mount(): void
    {
        $this->mode = TableOrdering::mode();
    }

    public function updatedMode(): void
    {
        $this->authorize('establishment.manage');

        if (! in_array($this->mode, TableOrdering::MODES, true)) {
            $this->mode = TableOrdering::mode();

            return;
        }

        Setting::write(TableOrdering::SETTING, $this->mode);
        Audit::record('table.qr_settings', changes: ['modo' => $this->mode]);
        $this->saved = true;
    }

    /** QR novo para a mesa: o impresso antigo para de funcionar (imprima de novo). */
    public function renew(int $tableId): void
    {
        $this->authorize('establishment.manage');

        $table = DiningTable::findOrFail($tableId);
        $table->renewQrToken();
        Audit::record('table.qr_renewed', $table, ['mesa' => $table->name]);
    }

    public function render()
    {
        $tables = DiningTable::where('is_active', true)->ordered()->get();

        return view('livewire.tenant.tables.qr-codes', [
            'tables' => $tables,
            'codes' => $tables->mapWithKeys(fn (DiningTable $table) => [$table->id => [
                'url' => TableOrdering::url($table),
                'svg' => QrCode::svg(TableOrdering::url($table)),
            ]]),
            'name' => Establishment::current()->trade_name ?: tenant()->name,
        ]);
    }
}
