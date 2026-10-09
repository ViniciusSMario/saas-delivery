<?php

namespace App\Livewire\Tenant\Tables;

use App\Modules\Tables\Actions\ManageCommand;
use App\Modules\Tables\Models\Command;
use App\Modules\Tables\Models\DiningTable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Mapa do salão: mesas livres, ocupadas e com a conta pedida. */
#[Title('Mesas')]
class Map extends Component
{
    public ?int $openingTableId = null;

    public int $people = 2;

    public string $label = '';

    public bool $openingLoose = false;

    public function startOpen(int $tableId): void
    {
        $this->openingTableId = $tableId;
        $this->openingLoose = false;
        $this->people = min(DiningTable::findOrFail($tableId)->seats, 2);
        $this->resetErrorBag();
    }

    public function startLoose(): void
    {
        $this->openingTableId = null;
        $this->openingLoose = true;
        $this->people = 1;
        $this->label = '';
        $this->resetErrorBag();
    }

    public function open(ManageCommand $manage): void
    {
        $this->authorize('tables.manage');

        try {
            $command = $manage->open(
                $this->openingTableId ? DiningTable::findOrFail($this->openingTableId) : null,
                $this->openingLoose ? $this->label : null,
                $this->people,
                Auth::guard('web')->user(),
            );
        } catch (ValidationException $e) {
            $this->addError('open', collect($e->errors())->flatten()->first());

            return;
        }

        $this->redirectRoute('tables.command', $command, navigate: true);
    }

    public function render()
    {
        $tables = DiningTable::where('is_active', true)->ordered()->with('openCommand')->get();
        $open = Command::open()->get()->keyBy('id');

        return view('livewire.tenant.tables.map', [
            'areas' => $tables->groupBy(fn ($t) => $t->area ?: 'Salão'),
            'subtotals' => $open->map(fn (Command $c) => $c->itemsSubtotal()),
            'loose' => Command::open()->whereNull('table_id')->latest()->get(),
            'hasTables' => DiningTable::exists(),
        ]);
    }
}
