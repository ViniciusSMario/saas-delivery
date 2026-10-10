<?php

namespace App\Livewire\Tenant\Printing;

use App\Modules\Access\Audit;
use App\Modules\Menu\Models\Category;
use App\Modules\Printing\Models\PrintJob;
use App\Modules\Printing\Models\PrintStation;
use App\Modules\Printing\OrderTickets;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Setores de produção, impressora de cada um e qual categoria sai em qual setor. */
#[Title('Impressoras')]
class Settings extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $mode = 'browser';

    public string $host = '';

    public int $port = 9100;

    public int $columns = 48;

    public int $copies = 1;

    public bool $printsFullOrder = false;

    public bool $isDefault = false;

    public bool $isActive = true;

    /** @var array<int, string> categoria => setor ('' = padrão) */
    public array $routing = [];

    public function mount(): void
    {
        $this->routing = Category::ordered()->pluck('print_station_id', 'id')->map(fn ($id) => (string) $id)->all();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'mode' => ['required', Rule::in(['browser', 'network'])],
            'host' => [Rule::requiredIf($this->mode === 'network'), 'nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9.\-]+$/'],
            // Só a porta RAW das impressoras: o servidor nunca vira ponte para outros serviços.
            'port' => ['required', 'integer', 'between:9100,9109'],
            'columns' => ['required', Rule::in([32, 42, 48])],
            'copies' => ['required', 'integer', 'between:1,3'],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['name' => 'nome', 'host' => 'endereço da impressora', 'port' => 'porta', 'columns' => 'papel', 'copies' => 'cópias'];
    }

    public function edit(int $id): void
    {
        $station = PrintStation::findOrFail($id);
        $this->editingId = $station->id;
        $this->fill([
            'name' => $station->name, 'mode' => $station->mode, 'host' => (string) $station->host, 'port' => $station->port,
            'columns' => $station->columns, 'copies' => $station->copies, 'printsFullOrder' => $station->prints_full_order,
            'isDefault' => $station->is_default, 'isActive' => $station->is_active,
        ]);
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'mode', 'host', 'port', 'columns', 'copies', 'printsFullOrder', 'isDefault', 'isActive');
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorize('settings.manage');
        $this->validate();

        if ($this->mode === 'network' && ! config('printing.network_enabled')) {
            $this->addError('mode', 'Impressora de rede só funciona com o sistema instalado na rede do restaurante. Use a estação de impressão.');

            return;
        }

        DB::connection('tenant')->transaction(function () {
            $station = $this->editingId ? PrintStation::findOrFail($this->editingId) : new PrintStation;
            $station->fill([
                'name' => trim($this->name), 'mode' => $this->mode, 'host' => $this->mode === 'network' ? trim($this->host) : null,
                'port' => $this->port, 'columns' => $this->columns, 'copies' => $this->copies,
                'prints_full_order' => $this->printsFullOrder, 'is_default' => $this->isDefault, 'is_active' => $this->isActive,
            ])->save();

            // Só um setor padrão; e sempre há um, se existir algum.
            if ($station->is_default) {
                PrintStation::whereKeyNot($station->id)->update(['is_default' => false]);
            } elseif (! PrintStation::where('is_default', true)->exists()) {
                $station->update(['is_default' => true]);
            }

            Audit::record($this->editingId ? 'print_station.updated' : 'print_station.created', null, ['setor' => $station->name]);
        });

        $this->cancel();
    }

    public function delete(int $id): void
    {
        $this->authorize('settings.manage');
        $station = PrintStation::findOrFail($id);
        $station->delete();
        Audit::record('print_station.deleted', null, ['setor' => $station->name]);

        if (! PrintStation::where('is_default', true)->exists()) {
            PrintStation::orderBy('id')->first()?->update(['is_default' => true]);
        }

        $this->mount();
    }

    public function updatedRouting(string $value, string $categoryId): void
    {
        $this->authorize('settings.manage');

        Category::whereKey((int) $categoryId)->update(['print_station_id' => $value !== '' ? (int) $value : null]);
    }

    public function test(int $id, OrderTickets $tickets): void
    {
        $this->authorize('settings.manage');

        $tickets->test(PrintStation::findOrFail($id));
        session()->flash('status', 'Cupom de teste enviado para a fila.');
    }

    public function render()
    {
        return view('livewire.tenant.printing.settings', [
            'stations' => PrintStation::orderBy('name')->withCount(['categories', 'jobs as pending_count' => fn ($q) => $q->waiting()])->get(),
            'categories' => Category::ordered()->get(),
            'networkEnabled' => (bool) config('printing.network_enabled'),
            'failed' => PrintJob::with('station')->where('status', 'failed')->latest('id')->limit(5)->get(),
        ]);
    }
}
