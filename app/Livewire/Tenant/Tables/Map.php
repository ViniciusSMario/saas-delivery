<?php

namespace App\Livewire\Tenant\Tables;

use App\Modules\Tables\Actions\ManageCommand;
use App\Modules\Tables\CommandStatus;
use App\Modules\Tables\Models\Command;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tables\ServeQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Mapa do salão: mesas livres, ocupadas e com a conta pedida. Feito para o celular do garçom. */
#[Layout('components.layouts.app', ['wide' => true])]
#[Title('Mesas')]
class Map extends Component
{
    /** all | free | busy | closing */
    public string $filter = 'all';

    public ?int $openingTableId = null;

    public int $people = 2;

    public string $label = '';

    public bool $openingLoose = false;

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'free', 'busy', 'closing'], true) ? $filter : 'all';
    }

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

    public function cancelOpen(): void
    {
        $this->reset('openingTableId', 'openingLoose', 'label');
    }

    public function open(ManageCommand $manage): void
    {
        $this->authorize('tables.manage');

        try {
            $command = $manage->open(
                $this->openingTableId ? DiningTable::findOrFail($this->openingTableId) : null,
                $this->openingLoose ? $this->label : null,
                max(1, min(99, $this->people)),
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
        $state = fn (DiningTable $t) => ! $t->openCommand ? 'free' : ($t->openCommand->status === CommandStatus::Closing ? 'closing' : 'busy');

        return view('livewire.tenant.tables.map', [
            'counts' => $tables->countBy($state)->all() + ['free' => 0, 'busy' => 0, 'closing' => 0],
            'areas' => $tables
                ->filter(fn ($t) => $this->filter === 'all' || $state($t) === $this->filter)
                ->groupBy(fn ($t) => $t->area ?: 'Salão'),
            'subtotals' => $open->map(fn (Command $c) => $c->itemsSubtotal()),
            'loose' => Command::open()->whereNull('table_id')->latest()->get(),
            'hasTables' => $tables->isNotEmpty(),
            'toServe' => ServeQueue::countFor(Auth::guard('web')->user()),
            'opening' => $this->openingTableId ? $tables->firstWhere('id', $this->openingTableId) : null,
        ]);
    }
}
