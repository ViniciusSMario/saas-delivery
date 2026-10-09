<?php

namespace App\Livewire\Tenant\Orders;

use App\Livewire\Tenant\Concerns\PicksMenuItems;
use App\Modules\Access\Models\User;
use App\Modules\Cash\CashLedger;
use App\Modules\Customers\Models\Customer;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Models\PaymentMethod;
use App\Modules\Tables\CommandStatus;
use App\Modules\Tables\Models\Command;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * PDV: venda de balcão (paga na hora, no caixa do operador), pedido por telefone
 * e atalho para receber comandas com a conta pedida.
 */
#[Title('PDV')]
class Counter extends Component
{
    use PicksMenuItems;

    public string $fulfillment = 'counter';

    public string $customerName = '';

    public string $customerPhone = '';

    /** Observações do cadastro do cliente (ex.: alergia), só para mostrar. */
    public string $customerNotes = '';

    /** @var array<string, string> */
    public array $address = ['street' => '', 'number' => '', 'complement' => '', 'district' => '', 'city' => '', 'reference' => ''];

    public string $paymentMethod = '';

    public string $changeFor = '';

    public string $discount = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->paymentMethod = (string) PaymentMethod::active()->value('code');
    }

    /** Cliente que já pediu: o telefone preenche nome e o último endereço (só o que está em branco). */
    public function updatedCustomerPhone(): void
    {
        if (! Customer::isValidPhone($this->customerPhone)) {
            return;
        }

        $customer = Customer::with('addresses')->where('phone', Customer::normalizePhone($this->customerPhone))->first();

        if (! $customer) {
            return;
        }

        $this->customerName = $this->customerName ?: $customer->name;
        $address = $customer->addresses->first();

        if ($address && collect($this->address)->filter()->isEmpty()) {
            $this->address = array_merge($this->address, array_map(fn ($v) => (string) $v, $address->only(['street', 'number', 'complement', 'district', 'city', 'reference'])));
        }

        $this->customerNotes = (string) $customer->notes;
    }

    public function clearCart(): void
    {
        $this->reset('cart', 'customerName', 'customerPhone', 'customerNotes', 'address', 'changeFor', 'discount', 'notes');
        $this->fulfillment = 'counter';
        $this->resetErrorBag();
    }

    public function checkout(PlaceOrder $placeOrder): void
    {
        $this->authorize('pos.use');
        $this->resetErrorBag();

        foreach (['changeFor' => $this->changeFor, 'discount' => $this->discount] as $field => $value) {
            if ($value !== '' && ! Money::isValid($value)) {
                $this->addError($field, 'Valor inválido.');

                return;
            }
        }

        try {
            $order = $placeOrder->handle([
                'lines' => $this->cartLines(),
                'fulfillment' => $this->fulfillment,
                'customer' => ['name' => $this->customerName, 'phone' => $this->customerPhone],
                'address' => $this->address,
                'payment_method' => $this->paymentMethod,
                'change_for' => $this->changeFor !== '' ? Money::parse($this->changeFor) : null,
                'discount' => $this->discount !== '' ? Money::parse($this->discount) : null,
                'notes' => $this->notes,
            ], OrderSource::Counter, $this->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError(str_starts_with($key, 'lines') ? 'cart' : $key, $messages[0]);
            }

            return;
        }

        $this->clearCart();
        session()->flash('status', "Pedido #{$order->number} criado · {$order->total_cents->format()}.");
        $this->redirectRoute('orders.counter', navigate: true);
    }

    private function user(): User
    {
        return Auth::guard('web')->user();
    }

    public function render()
    {
        $ledger = app(CashLedger::class);
        $subtotal = $this->cartSubtotal()->cents;
        $fee = $this->fulfillment === 'delivery' ? (int) Setting::read('delivery_fee_cents', 0) : 0;
        $discount = $this->discount !== '' && Money::isValid($this->discount) ? Money::parse($this->discount)->cents : 0;

        return view('livewire.tenant.orders.counter', $this->pickerData() + [
            'paymentMethods' => PaymentMethod::active()->get(),
            'subtotal' => Money::cents($subtotal),
            'fee' => Money::cents($fee),
            'total' => Money::cents(max(0, $subtotal - $discount + $fee)),
            'canDiscount' => $this->user()->can('orders.discount'),
            'session' => $ledger->currentFor($this->user()),
            'awaiting' => Command::where('status', CommandStatus::Closing)->with('diningTable')->orderBy('updated_at')->get(),
        ]);
    }
}
