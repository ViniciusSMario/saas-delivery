<?php

namespace App\Livewire\Tenant\Establishment;

use App\Modules\Access\Audit;
use App\Modules\Establishment\Models\Setting;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Configurações')]
class EditSettings extends Component
{
    public string $welcome_message = '';

    public string $timezone = 'America/Sao_Paulo';

    public bool $accepts_delivery = true;

    public bool $accepts_pickup = true;

    /** Valores em reais na tela; gravados em centavos. */
    public string $min_order = '0,00';

    public string $delivery_fee = '0,00';

    public const TIMEZONES = [
        'America/Sao_Paulo' => 'Brasília (Sudeste, Sul, Nordeste, GO, DF)',
        'America/Manaus' => 'Amazonas, Rondônia, Roraima, MT, MS',
        'America/Rio_Branco' => 'Acre',
        'America/Noronha' => 'Fernando de Noronha',
    ];

    public function mount(): void
    {
        $this->welcome_message = (string) Setting::read('welcome_message', '');
        $this->timezone = (string) Setting::read('timezone', 'America/Sao_Paulo');
        $this->accepts_delivery = (bool) Setting::read('accepts_delivery', true);
        $this->accepts_pickup = (bool) Setting::read('accepts_pickup', true);
        $this->min_order = $this->toMoney((int) Setting::read('min_order_cents', 0));
        $this->delivery_fee = $this->toMoney((int) Setting::read('delivery_fee_cents', 0));
    }

    protected function rules(): array
    {
        return [
            'welcome_message' => ['required', 'string', 'max:160'],
            'timezone' => ['required', 'in:'.implode(',', array_keys(self::TIMEZONES))],
            'accepts_delivery' => ['boolean'],
            'accepts_pickup' => ['boolean'],
            'min_order' => ['required', 'regex:/^\d{1,6}([,.]\d{1,2})?$/'],
            'delivery_fee' => ['required', 'regex:/^\d{1,6}([,.]\d{1,2})?$/'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'welcome_message' => 'mensagem de boas-vindas',
            'timezone' => 'fuso horário',
            'min_order' => 'pedido mínimo',
            'delivery_fee' => 'taxa de entrega',
        ];
    }

    public function save(): void
    {
        $this->authorize('settings.manage');
        $this->validate();

        $values = [
            'welcome_message' => $this->welcome_message,
            'timezone' => $this->timezone,
            'accepts_delivery' => $this->accepts_delivery,
            'accepts_pickup' => $this->accepts_pickup,
            'min_order_cents' => $this->toCents($this->min_order),
            'delivery_fee_cents' => $this->toCents($this->delivery_fee),
        ];

        $changes = [];

        foreach ($values as $key => $value) {
            $old = Setting::read($key);

            if ($old !== $value) {
                Setting::write($key, $value);
                $changes[$key] = ['de' => $old, 'para' => $value];
            }
        }

        if ($changes !== []) {
            Audit::record('settings.updated', null, $changes);
        }

        session()->flash('status', 'Configurações salvas.');
        $this->redirectRoute('settings.edit', navigate: true);
    }

    private function toCents(string $value): int
    {
        return (int) round(((float) str_replace(',', '.', $value)) * 100);
    }

    private function toMoney(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '');
    }

    public function render()
    {
        return view('livewire.tenant.establishment.edit-settings', ['timezones' => self::TIMEZONES]);
    }
}
