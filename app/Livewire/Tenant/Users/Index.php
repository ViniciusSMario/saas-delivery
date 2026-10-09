<?php

namespace App\Livewire\Tenant\Users;

use App\Modules\Access\Actions\SendSetPasswordLink;
use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Usuários')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = 'active';

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function resendInvite(int $userId, SendSetPasswordLink $send): void
    {
        $this->authorize('users.manage');

        $user = User::findOrFail($userId);
        $send->handle($user, invitation: true);
        Audit::record('user.invited', $user, ['email' => $user->email]);

        session()->flash('status', "Link para definir a senha enviado para {$user->email}.");
        $this->redirectRoute('users.index', navigate: true);
    }

    public function render()
    {
        $users = User::query()
            ->with('roles')
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(fn ($q) => $q->whereRaw('lower(name) like ?', [$term])->orWhere('email', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.tenant.users.index', ['users' => $users]);
    }
}
