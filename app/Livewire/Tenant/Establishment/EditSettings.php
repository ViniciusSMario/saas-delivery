<?php

namespace App\Livewire\Tenant\Establishment;

use App\Modules\Access\Audit;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Models\PaymentMethod;
use App\Modules\Orders\OrderEta;
use App\Modules\Push\StaffAlerts;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Configurações')]
class EditSettings extends Component
{
    public string $welcome_message = '';

    public string $timezone = 'America/Sao_Paulo';

    public bool $accepts_online_orders = true;

    public bool $accepts_delivery = true;

    public bool $accepts_pickup = true;

    public bool $service_fee_enabled = true;

    public int $service_fee_percent = 10;

    public bool $stock_auto_sold_out = false;

    /** Minutos sem aceite até avisar a gerência no celular (0 = não avisa). */
    public int $order_waiting_alert_minutes = StaffAlerts::WAITING_MINUTES_DEFAULT;

    /** Previsão mostrada ao cliente (0 = não mostrar). */
    public int $eta_delivery_minutes = OrderEta::DELIVERY_DEFAULT;

    public int $eta_pickup_minutes = OrderEta::PICKUP_DEFAULT;

    /** @var array<int, bool> id da forma de pagamento => ativa */
    public array $paymentMethods = [];

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
        $this->accepts_online_orders = (bool) Setting::read('accepts_online_orders', true);
        $this->accepts_delivery = (bool) Setting::read('accepts_delivery', true);
        $this->paymentMethods = PaymentMethod::orderBy('position')->pluck('is_active', 'id')->map(fn ($v) => (bool) $v)->all();
        $this->accepts_pickup = (bool) Setting::read('accepts_pickup', true);
        $this->service_fee_enabled = (bool) Setting::read('service_fee_enabled', true);
        $this->service_fee_percent = (int) Setting::read('service_fee_percent', 10);
        $this->stock_auto_sold_out = (bool) Setting::read('stock_auto_sold_out', false);
        $this->order_waiting_alert_minutes = StaffAlerts::waitingMinutes();
        $this->eta_delivery_minutes = (int) Setting::read(OrderEta::DELIVERY_SETTING, OrderEta::DELIVERY_DEFAULT);
        $this->eta_pickup_minutes = (int) Setting::read(OrderEta::PICKUP_SETTING, OrderEta::PICKUP_DEFAULT);
        $this->min_order = $this->toMoney((int) Setting::read('min_order_cents', 0));
        $this->delivery_fee = $this->toMoney((int) Setting::read('delivery_fee_cents', 0));
    }

    protected function rules(): array
    {
        return [
            'welcome_message' => ['required', 'string', 'max:160'],
            'timezone' => ['required', 'in:'.implode(',', array_keys(self::TIMEZONES))],
            'accepts_online_orders' => ['boolean'],
            'accepts_delivery' => ['boolean'],
            'paymentMethods' => ['array', fn ($a, $v, $fail) => in_array(true, (array) $v, true) ? null : $fail('Deixe pelo menos uma forma de pagamento ativa.')],
            'accepts_pickup' => ['boolean'],
            'service_fee_enabled' => ['boolean'],
            'service_fee_percent' => ['required', 'integer', 'min:0', 'max:30'],
            'stock_auto_sold_out' => ['boolean'],
            'order_waiting_alert_minutes' => ['required', 'integer', 'min:0', 'max:60'],
            'eta_delivery_minutes' => ['required', 'integer', 'min:0', 'max:300'],
            'eta_pickup_minutes' => ['required', 'integer', 'min:0', 'max:300'],
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
            'order_waiting_alert_minutes' => 'minutos sem aceite',
            'eta_delivery_minutes' => 'tempo de entrega',
            'eta_pickup_minutes' => 'tempo de retirada',
        ];
    }

    public function save(): void
    {
        $this->authorize('settings.manage');
        $this->validate();

        $values = [
            'welcome_message' => $this->welcome_message,
            'timezone' => $this->timezone,
            'accepts_online_orders' => $this->accepts_online_orders,
            'accepts_delivery' => $this->accepts_delivery,
            'accepts_pickup' => $this->accepts_pickup,
            'service_fee_enabled' => $this->service_fee_enabled,
            'service_fee_percent' => $this->service_fee_percent,
            'stock_auto_sold_out' => $this->stock_auto_sold_out,
            StaffAlerts::WAITING_MINUTES_SETTING => $this->order_waiting_alert_minutes,
            OrderEta::DELIVERY_SETTING => $this->eta_delivery_minutes,
            OrderEta::PICKUP_SETTING => $this->eta_pickup_minutes,
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

        foreach (PaymentMethod::all() as $method) {
            $active = (bool) ($this->paymentMethods[$method->id] ?? false);

            if ($method->is_active !== $active) {
                $method->update(['is_active' => $active]);
                $changes['pagamento: '.$method->name] = $active ? 'ativado' : 'desativado';
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
        return view('livewire.tenant.establishment.edit-settings', [
            'timezones' => self::TIMEZONES,
            'methods' => PaymentMethod::orderBy('position')->get(),
        ]);
    }
}
