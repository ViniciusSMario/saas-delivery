<?php

namespace App\Livewire\Tenant;

use App\Modules\Establishment\Models\Setting;
use Livewire\Attributes\Validate;
use Livewire\Component;

/** Componente mínimo que prova o fluxo Livewire dentro do tenant. */
class WelcomeMessage extends Component
{
    #[Validate('required|string|max:120')]
    public string $draft = '';

    public function mount(): void
    {
        $this->draft = (string) Setting::read('welcome_message', '');
    }

    public function save(): void
    {
        $this->validate();

        Setting::write('welcome_message', $this->draft);
    }

    public function render()
    {
        return view('livewire.tenant.welcome-message', [
            'message' => Setting::read('welcome_message', ''),
        ]);
    }
}
