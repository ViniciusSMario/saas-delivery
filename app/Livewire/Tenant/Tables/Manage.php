<?php

namespace App\Livewire\Tenant\Tables;

use App\Modules\Access\Audit;
use App\Modules\Tables\Models\DiningTable;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Cadastro de mesas (dono/gerente). */
#[Title('Cadastro de mesas')]
class Manage extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public int $seats = 4;

    public string $area = '';

    public bool $is_active = true;

    /** Criar várias de uma vez: "1-20". */
    public string $range = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:20', Rule::unique('tenant.tables', 'name')->ignore($this->editingId)],
            'seats' => ['required', 'integer', 'min:1', 'max:99'],
            'area' => ['nullable', 'string', 'max:40'],
            'is_active' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['name' => 'número/nome', 'seats' => 'lugares', 'area' => 'área'];
    }

    public function edit(int $id): void
    {
        $table = DiningTable::findOrFail($id);
        $this->editingId = $table->id;
        $this->fill($table->only(['name', 'seats', 'is_active']));
        $this->area = (string) $table->area;
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'seats', 'area', 'is_active');
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->authorize('establishment.manage');
        $this->validate();

        if (! $this->editingId) {
            tenant()->assertWithinLimit('tables', DiningTable::count(), 'name');
        }

        $table = $this->editingId ? DiningTable::findOrFail($this->editingId) : new DiningTable(['position' => (DiningTable::max('position') ?? 0) + 1]);
        $table->fill(['name' => trim($this->name), 'seats' => $this->seats, 'area' => trim($this->area) ?: null, 'is_active' => $this->is_active])->save();
        Audit::record($this->editingId ? 'table.updated' : 'table.created', $table, ['mesa' => $table->name]);

        $this->cancel();
    }

    public function createRange(): void
    {
        $this->authorize('establishment.manage');
        $this->resetErrorBag('range');

        if (! preg_match('/^(\d{1,3})\s*-\s*(\d{1,3})$/', trim($this->range), $m) || (int) $m[1] > (int) $m[2] || (int) $m[2] - (int) $m[1] > 99) {
            $this->addError('range', 'Use o formato inicial-final, ex.: 1-20 (até 100 mesas).');

            return;
        }

        $existing = DiningTable::pluck('name')->all();
        $missing = count(array_diff(array_map('strval', range((int) $m[1], (int) $m[2])), $existing));
        tenant()->assertWithinLimit('tables', count($existing), 'range', $missing);

        $position = (DiningTable::max('position') ?? 0);
        $created = 0;

        foreach (range((int) $m[1], (int) $m[2]) as $number) {
            $table = DiningTable::firstOrCreate(['name' => (string) $number], ['seats' => $this->seats, 'area' => trim($this->area) ?: null, 'position' => ++$position]);
            $created += $table->wasRecentlyCreated ? 1 : 0;
        }

        Audit::record('table.created', null, ['mesas' => $this->range, 'criadas' => $created]);
        $this->reset('range');
    }

    public function render()
    {
        return view('livewire.tenant.tables.manage', [
            'tables' => DiningTable::ordered()->withCount('commands')->get(),
        ]);
    }
}
