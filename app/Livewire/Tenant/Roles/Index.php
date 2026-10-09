<?php

namespace App\Livewire\Tenant\Roles;

use App\Modules\Access\Actions\SaveRole;
use App\Modules\Access\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Perfis e permissões')]
class Index extends Component
{
    public function delete(int $roleId, SaveRole $save): void
    {
        $this->authorize('roles.manage');

        try {
            $save->delete(Role::findOrFail($roleId), Auth::guard('web')->user());
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first());
            $this->redirectRoute('roles.index', navigate: true);

            return;
        }

        session()->flash('status', 'Perfil excluído.');
        $this->redirectRoute('roles.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.tenant.roles.index', [
            'roles' => Role::withCount('users')->orderByDesc('is_owner')->orderByDesc('is_system')->orderBy('name')->get(),
        ]);
    }
}
