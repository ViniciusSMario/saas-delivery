<?php

namespace App\Livewire\Platform\Tenants;

use App\Platform\Actions\OnboardTenant;
use App\Platform\Models\Plan;
use App\Tenancy\Actions\ProvisionTenant;
use App\Tenancy\Exceptions\InvalidTenantData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.platform')]
#[Title('Novo estabelecimento')]
class Create extends Component
{
    public string $name = '';

    public string $slug = '';

    public string $document = '';

    public ?int $plan_id = null;

    public string $owner_name = '';

    public string $owner_email = '';

    public bool $slugTouched = false;

    public function updatedName(): void
    {
        if (! $this->slugTouched) {
            $this->slug = Str::limit(Str::slug($this->name), 40, '');
        }
    }

    public function updatedSlug(): void
    {
        $this->slugTouched = true;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'regex:'.ProvisionTenant::SLUG_PATTERN],
            'document' => ['nullable', 'regex:/^\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}$/'],
            'plan_id' => ['nullable', 'exists:central.plans,id'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:160'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'nome', 'slug' => 'endereço', 'document' => 'CNPJ', 'plan_id' => 'plano',
            'owner_name' => 'nome do dono', 'owner_email' => 'e-mail do dono',
        ];
    }

    protected function messages(): array
    {
        return [
            'slug.regex' => 'Use de 3 a 40 letras minúsculas, números ou hífen (sem começar ou terminar com hífen).',
            'document.regex' => 'Informe um CNPJ no formato 00.000.000/0000-00.',
        ];
    }

    public function save(OnboardTenant $onboard): void
    {
        $this->authorize('platform.manage-tenants');
        $this->validate();

        try {
            $tenant = $onboard->handle(
                slug: $this->slug,
                name: $this->name,
                document: $this->document ?: null,
                ownerName: $this->owner_name,
                ownerEmail: $this->owner_email,
                planId: $this->plan_id,
                actor: Auth::guard('platform')->user()->email,
            );
        } catch (InvalidTenantData $e) {
            throw ValidationException::withMessages(['slug' => $e->getMessage()]);
        }

        session()->flash('status', "{$tenant->name} criado. O dono recebeu um e-mail para definir a senha.");
        $this->redirectRoute('platform.tenants.show', $tenant, navigate: true);
    }

    public function render()
    {
        return view('livewire.platform.tenants.create', [
            'plans' => Plan::orderBy('price_cents')->get(),
            'baseDomain' => config('tenancy.base_domain'),
        ]);
    }
}
