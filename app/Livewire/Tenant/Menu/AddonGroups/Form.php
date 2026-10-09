<?php

namespace App\Livewire\Tenant\Menu\AddonGroups;

use App\Modules\Menu\Actions\SaveAddonGroup;
use App\Modules\Menu\Models\AddonGroup;
use App\Support\Money;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?int $groupId = null;

    public string $name = '';

    public int $min_choices = 0;

    public int $max_choices = 1;

    public bool $is_active = true;

    /** @var list<array{id: int|null, name: string, price: string, is_active: bool}> */
    public array $addons = [];

    public function mount(?AddonGroup $group = null): void
    {
        if ($group?->exists) {
            $this->groupId = $group->id;
            $this->name = $group->name;
            $this->min_choices = $group->min_choices;
            $this->max_choices = $group->max_choices;
            $this->is_active = $group->is_active;
            $this->addons = $group->addons->map(fn ($addon) => [
                'id' => $addon->id,
                'name' => $addon->name,
                'price' => $addon->price_cents->decimal(),
                'is_active' => $addon->is_active,
            ])->all();
        }

        if ($this->addons === []) {
            $this->addOption();
        }
    }

    public function addOption(): void
    {
        $this->addons[] = ['id' => null, 'name' => '', 'price' => '0,00', 'is_active' => true];
    }

    public function removeOption(int $index): void
    {
        unset($this->addons[$index]);
        $this->addons = array_values($this->addons);
    }

    protected function rules(): array
    {
        $money = fn ($attr, $value, $fail) => Money::isValid($value) && Money::parse($value)->cents >= 0
            ? null
            : $fail('Preço inválido.');

        return [
            'name' => ['required', 'string', 'max:80'],
            'min_choices' => ['required', 'integer', 'min:0', 'max:50'],
            'max_choices' => ['required', 'integer', 'min:1', 'max:50', 'gte:min_choices'],
            'is_active' => ['boolean'],
            'addons' => ['required', 'array', 'min:1', 'max:60'],
            'addons.*.name' => ['required', 'string', 'max:80'],
            'addons.*.price' => ['required', $money],
            'addons.*.is_active' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'nome', 'min_choices' => 'mínimo', 'max_choices' => 'máximo',
            'addons' => 'opções', 'addons.*.name' => 'nome da opção', 'addons.*.price' => 'preço',
        ];
    }

    public function save(SaveAddonGroup $save): void
    {
        $this->authorize('menu.manage');
        $this->validate();

        $save->handle(
            $this->groupId ? AddonGroup::findOrFail($this->groupId) : null,
            ['name' => $this->name, 'min_choices' => $this->min_choices, 'max_choices' => $this->max_choices, 'is_active' => $this->is_active],
            array_map(fn (array $row) => [
                'id' => $row['id'] ?? null,
                'name' => $row['name'],
                'price' => Money::parse($row['price']),
                'is_active' => (bool) $row['is_active'],
            ], $this->addons),
        );

        session()->flash('status', 'Grupo de adicionais salvo.');
        $this->redirectRoute('menu.addon-groups.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.tenant.menu.addon-groups.form')
            ->title($this->groupId ? 'Editar adicionais' : 'Novo grupo de adicionais');
    }
}
