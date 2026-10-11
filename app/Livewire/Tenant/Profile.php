<?php

namespace App\Livewire\Tenant;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use App\Modules\Push\StaffAlerts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Minha conta')]
class Profile extends Component
{
    public string $name = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** @var array<string, bool> avisos no celular ligados (tipo => sim/não) */
    public array $alerts = [];

    public function mount(): void
    {
        $this->name = $this->user()->name;

        foreach (array_keys(StaffAlerts::choicesFor($this->user())) as $type) {
            $this->alerts[$type] = StaffAlerts::wants($this->user(), $type);
        }
    }

    /** Cada caixinha salva na hora. */
    public function updatedAlerts(): void
    {
        $user = $this->user();
        $choices = array_keys(StaffAlerts::choicesFor($user));
        $chosen = array_intersect_key(array_map(fn ($on) => (bool) $on, $this->alerts), array_flip($choices));

        $user->forceFill(['alert_preferences' => array_merge($user->alert_preferences ?? [], $chosen)])->save();
        $this->dispatch('alerts-saved');
    }

    public function saveName(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:120']], attributes: ['name' => 'nome']);

        $user = $this->user();
        $user->update(['name' => $this->name]);

        if ($user->wasChanged()) {
            Audit::record('user.updated', $user, Audit::diff($user), $user);
        }

        session()->flash('status', 'Nome atualizado.');
        $this->redirectRoute('profile', navigate: true);
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], attributes: ['current_password' => 'senha atual', 'password' => 'nova senha']);

        $user = $this->user();
        $user->forceFill(['password' => $this->password, 'remember_token' => Str::random(60)])->save();
        Audit::record('auth.password_reset', $user, actor: $user);

        // AuthenticateSession derruba as outras sessões (o hash da senha mudou);
        // esta continua válida com o hash novo.
        session()->put('password_hash_web', $user->getAuthPassword());

        $this->reset('current_password', 'password', 'password_confirmation');
        session()->flash('status', 'Senha alterada.');
        $this->redirectRoute('profile', navigate: true);
    }

    private function user(): User
    {
        return Auth::guard('web')->user();
    }

    public function render()
    {
        return view('livewire.tenant.profile');
    }
}
