<?php

namespace App\Livewire\Tenant\Delivery;

use App\Livewire\Tenant\Concerns\Toasts;
use App\Modules\Access\Audit;
use App\Modules\Delivery\DeliveryFees;
use App\Modules\Delivery\Models\DeliveryZone;
use App\Modules\Establishment\Models\Setting;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Taxa de entrega: valor único ou por bairro (com pedido mínimo e tempo por bairro). */
#[Title('Bairros e taxas de entrega')]
class Zones extends Component
{
    use Toasts;

    public string $mode = DeliveryFees::MODE_FIXED;

    public ?int $editingId = null;

    public string $name = '';

    public string $fee = '';

    public string $minOrder = '';

    public string $eta = '';

    public function mount(): void
    {
        $this->mode = DeliveryFees::mode();
    }

    public function updatedMode(string $value): void
    {
        $this->authorize('settings.manage');

        if ($value === DeliveryFees::MODE_ZONES && ! DeliveryZone::active()->exists()) {
            $this->mode = DeliveryFees::MODE_FIXED;
            $this->addError('mode', 'Cadastre pelo menos um bairro antes de cobrar por bairro.');

            return;
        }

        Setting::write('delivery_fee_mode', $value === DeliveryFees::MODE_ZONES ? DeliveryFees::MODE_ZONES : DeliveryFees::MODE_FIXED);
        Audit::record('settings.updated', null, ['taxa_de_entrega' => $value === DeliveryFees::MODE_ZONES ? 'por bairro' : 'valor único']);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('tenant.delivery_zones', 'name')->ignore($this->editingId)],
            'fee' => ['required', 'regex:/^\d{1,5}([,.]\d{1,2})?$/'],
            'minOrder' => ['nullable', 'regex:/^\d{1,5}([,.]\d{1,2})?$/'],
            'eta' => ['nullable', 'integer', 'between:5,300'],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['name' => 'bairro', 'fee' => 'taxa', 'minOrder' => 'pedido mínimo', 'eta' => 'tempo'];
    }

    public function edit(int $id): void
    {
        $zone = DeliveryZone::findOrFail($id);
        $this->editingId = $zone->id;
        $this->name = $zone->name;
        $this->fee = number_format($zone->fee_cents->cents / 100, 2, ',', '');
        $this->minOrder = $zone->min_order_cents ? number_format($zone->min_order_cents->cents / 100, 2, ',', '') : '';
        $this->eta = (string) ($zone->eta_minutes ?? '');
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'fee', 'minOrder', 'eta');
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorize('settings.manage');
        $this->validate();

        $zone = $this->editingId ? DeliveryZone::findOrFail($this->editingId) : new DeliveryZone;
        $zone->fill([
            'name' => trim(preg_replace('/\s+/', ' ', $this->name)),
            'fee_cents' => Money::parse($this->fee),
            'min_order_cents' => $this->minOrder !== '' ? Money::parse($this->minOrder) : null,
            'eta_minutes' => $this->eta !== '' ? (int) $this->eta : null,
        ])->save();

        Audit::record($this->editingId ? 'delivery_zone.updated' : 'delivery_zone.created', null, ['bairro' => $zone->name, 'taxa' => $zone->fee_cents->format()]);
        $this->cancel();
    }

    public function toggle(int $id): void
    {
        $this->authorize('settings.manage');
        $zone = DeliveryZone::findOrFail($id);
        $zone->update(['is_active' => ! $zone->is_active]);
        $this->toast($zone->is_active ? "Bairro {$zone->name} ativado." : "Bairro {$zone->name} pausado.", 'info', undo: 'toggle', params: [$id]);
    }

    public function delete(int $id): void
    {
        $this->authorize('settings.manage');
        $zone = DeliveryZone::findOrFail($id);
        $zone->delete();
        Audit::record('delivery_zone.deleted', null, ['bairro' => $zone->name]);

        if (! DeliveryZone::active()->exists()) {
            Setting::write('delivery_fee_mode', DeliveryFees::MODE_FIXED);
            $this->mode = DeliveryFees::MODE_FIXED;
        }
    }

    public function render()
    {
        return view('livewire.tenant.delivery.zones', [
            'zones' => DeliveryZone::ordered()->get(),
            'fixedFee' => Money::cents((int) Setting::read('delivery_fee_cents', 0)),
        ]);
    }
}
