<?php

namespace App\Livewire\Tenant\Roles;

use App\Modules\Access\Actions\SaveRole;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?int $roleId = null;

    public string $name = '';

    public string $description = '';

    /** @var list<string> */
    public array $permissions = [];

    public function mount(?Role $role = null): void
    {
        if ($role?->exists) {
            abort_if($role->is_owner, 403, 'O perfil de dono tem todas as permissões e não pode ser alterado.');

            $this->roleId = $role->id;
            $this->name = $role->name;
            $this->description = (string) $role->description;
            $this->permissions = array_map(fn (Permission $p) => $p->value, $role->permissions());
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:160'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permission::values())],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['name' => 'nome', 'description' => 'descrição', 'permissions' => 'permissões'];
    }

    public function save(SaveRole $save): void
    {
        $this->authorize('roles.manage');
        $this->validate();

        $save->handle(
            $this->roleId ? Role::findOrFail($this->roleId) : null,
            ['name' => $this->name, 'description' => $this->description ?: null],
            $this->permissions,
            Auth::guard('web')->user(),
        );

        session()->flash('status', 'Perfil salvo.');
        $this->redirectRoute('roles.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.tenant.roles.form', [
            'groups' => Permission::grouped(),
        ])->title($this->roleId ? 'Editar perfil' : 'Novo perfil');
    }
}
