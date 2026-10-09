<?php

namespace App\Livewire\Tenant\Users;

use App\Modules\Access\Actions\SaveUser;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    public bool $is_active = true;

    /** @var list<int> */
    public array $roleIds = [];

    public function mount(?User $user = null): void
    {
        if ($user?->exists) {
            $this->userId = $user->id;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->is_active = $user->is_active;
            $this->roleIds = $user->roles()->pluck('roles.id')->all();
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('tenant.users', 'email')->ignore($this->userId)],
            'is_active' => ['boolean'],
            'roleIds' => ['array'],
            'roleIds.*' => ['integer', Rule::exists('tenant.roles', 'id')],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['name' => 'nome', 'email' => 'e-mail', 'roleIds' => 'perfis'];
    }

    public function save(SaveUser $save): void
    {
        $this->authorize('users.manage');
        $this->email = mb_strtolower(trim($this->email));
        $this->validate();

        $user = $this->userId ? User::findOrFail($this->userId) : null;

        $save->handle(
            $user,
            ['name' => $this->name, 'email' => $this->email, 'is_active' => $this->is_active],
            array_map('intval', $this->roleIds),
            Auth::guard('web')->user(),
        );

        session()->flash('status', $user
            ? 'Usuário atualizado.'
            : "Usuário criado. Enviamos para {$this->email} um link para definir a senha.");

        $this->redirectRoute('users.index', navigate: true);
    }

    public function render()
    {
        $actor = Auth::guard('web')->user();

        return view('livewire.tenant.users.form', [
            'roles' => Role::orderByDesc('is_owner')->orderBy('name')->get()
                ->map(fn (Role $role) => ['role' => $role, 'assignable' => $actor->canAssignRole($role)]),
            'isSelf' => $this->userId === $actor->id,
        ])->title($this->userId ? 'Editar usuário' : 'Novo usuário');
    }
}
