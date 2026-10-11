<?php

namespace App\Livewire\Tenant\Orders;

use App\Livewire\Tenant\Concerns\KeepsDraft;
use App\Livewire\Tenant\Concerns\PicksMenuItems;
use App\Modules\Access\Models\User;
use App\Modules\Cash\CashLedger;
use App\Modules\Customers\Models\Customer;
use App\Modules\Delivery\DeliveryFees;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Models\PaymentMethod;
use App\Modules\Promotions\CouponRules;
use App\Modules\Promotions\Models\Coupon;
use App\Modules\Tables\CommandStatus;
use App\Modules\Tables\Models\Command;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * PDV: venda de balcão (paga na hora, no caixa do operador), pedido por telefone
 * e atalho para receber comandas com a conta pedida.
 */
#[Layout('components.layouts.app', ['wide' => true])]
#[Title('PDV')]
class Counter extends Component
{
    use KeepsDraft, PicksMenuItems;

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

    /** Código digitado no campo de cupom. */
    public string $couponInput = '';

    /** Cupom aplicado (código); o desconto é conferido de novo ao finalizar. */
    public string $coupon = '';

    public function mount(): void
    {
        $this->paymentMethod = (string) PaymentMethod::active()->value('code');
        $this->restoreDraft();
    }

    protected function draftKey(): string
    {
        return 'pdv-draft';
    }

    protected function draftFields(): array
    {
        return ['cart', 'fulfillment', 'customerName', 'customerPhone', 'customerNotes', 'address', 'paymentMethod', 'changeFor', 'discount', 'notes', 'coupon'];
    }

    protected function draftHasContent(): bool
    {
        return ! empty($this->cart) || trim($this->customerName) !== '' || trim($this->customerPhone) !== '';
    }

    public function applyCoupon(): void
    {
        $this->resetErrorBag('coupon');
        $coupon = Coupon::findByCode($this->couponInput);

        if (! $coupon) {
            $this->addError('coupon', 'Cupom não encontrado. Confira o código.');

            return;
        }

        if ($this->discount !== '') {
            $this->addError('coupon', 'Use o cupom ou o desconto manual, não os dois.');

            return;
        }

        $this->coupon = $coupon->code;
        $this->couponInput = '';
    }

    public function removeCoupon(): void
    {
        $this->coupon = '';
        $this->resetErrorBag('coupon');
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
        $this->reset('cart', 'customerName', 'customerPhone', 'customerNotes', 'address', 'changeFor', 'discount', 'notes', 'coupon', 'couponInput');
        $this->fulfillment = 'counter';
        $this->resetErrorBag();
        $this->forgetDraft();
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
                'coupon' => $this->coupon,
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
        $fees = app(DeliveryFees::class);
        $quote = $this->fulfillment === 'delivery' ? $fees->quote($this->address['district'] ?? '') : Money::zero();
        $fee = $quote?->cents ?? 0;
        $discount = $this->discount !== '' && Money::isValid($this->discount) ? Money::parse($this->discount)->cents : 0;

        // Prévia do cupom: mesmas regras do pedido (o valor final é conferido ao finalizar).
        $coupon = $this->coupon !== '' ? Coupon::findByCode($this->coupon) : null;
        $couponProblem = $coupon ? app(CouponRules::class)->problem($coupon, Money::cents($subtotal), $this->customerPhone ?: null, Fulfillment::tryFrom($this->fulfillment), requirePhone: true) : null;

        if ($coupon && ! $couponProblem) {
            $discount = $coupon->discountFor(Money::cents($subtotal), Money::cents($fee))->cents;
        }

        return view('livewire.tenant.orders.counter', $this->pickerData() + [
            'appliedCoupon' => $coupon,
            'couponProblem' => $this->coupon !== '' && ! $coupon ? 'Este cupom não existe mais.' : $couponProblem,
            'couponDiscount' => Money::cents($coupon && ! $couponProblem ? $discount : 0),
            'paymentMethods' => PaymentMethod::active()->get(),
            'subtotal' => Money::cents($subtotal),
            'fee' => Money::cents($fee),
            'zones' => DeliveryFees::byZone() ? $fees->zones() : collect(),
            'unknownZone' => $this->fulfillment === 'delivery' && $quote === null && trim($this->address['district'] ?? '') !== '',
            'total' => Money::cents(max(0, $subtotal - $discount + $fee)),
            'canDiscount' => $this->user()->can('orders.discount'),
            'session' => $ledger->currentFor($this->user()),
            'awaiting' => Command::where('status', CommandStatus::Closing)->with('diningTable')->orderBy('updated_at')->get(),
        ]);
    }
}
