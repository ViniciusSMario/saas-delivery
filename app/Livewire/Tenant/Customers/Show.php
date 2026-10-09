<?php

namespace App\Livewire\Tenant\Customers;

use App\Modules\Access\Audit;
use App\Modules\Customers\Actions\AnonymizeCustomer;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use WithPagination;

    #[Locked]
    public int $customerId;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $notes = '';

    public function mount(Customer $customer): void
    {
        $this->customerId = $customer->id;
        $this->fill($customer->only(['name', 'phone']));
        $this->phone = Customer::formatPhone($customer->phone);
        $this->email = (string) $customer->email;
        $this->notes = (string) $customer->notes;
    }

    public function save(): void
    {
        $this->authorize('customers.manage');
        $phone = Customer::normalizePhone($this->phone);

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'phone' => [fn ($a, $v, $fail) => Customer::isValidPhone($v) ? null : $fail('Telefone inválido.')],
        ], attributes: ['name' => 'nome', 'notes' => 'observações']);

        if (Customer::withTrashed()->where('phone', $phone)->whereKeyNot($this->customerId)->exists()) {
            $this->addError('phone', 'Já existe outro cliente com este telefone.');

            return;
        }

        $customer = Customer::findOrFail($this->customerId);
        $customer->update(['name' => trim($this->name), 'phone' => $phone, 'email' => $this->email ?: null, 'notes' => $this->notes ?: null]);

        if ($customer->wasChanged()) {
            Audit::record('customer.updated', $customer, Audit::diff($customer));
        }

        session()->flash('status', 'Cliente salvo.');
    }

    public function removeAddress(int $id): void
    {
        $this->authorize('customers.manage');
        CustomerAddress::where('customer_id', $this->customerId)->findOrFail($id)->delete();
    }

    public function anonymize(AnonymizeCustomer $action): void
    {
        $this->authorize('customers.manage');
        $action->handle(Customer::findOrFail($this->customerId), Auth::guard('web')->user());

        session()->flash('status', 'Dados pessoais do cliente apagados. Os pedidos continuam no histórico, sem identificação.');
        $this->redirectRoute('customers.index', navigate: true);
    }

    public function render()
    {
        $customer = Customer::with('addresses')->findOrFail($this->customerId);
        $valid = Order::where('customer_id', $customer->id)->where('status', '!=', OrderStatus::Cancelled->value);
        $count = (clone $valid)->count();
        $spent = (int) (clone $valid)->sum('total_cents');

        return view('livewire.tenant.customers.show', [
            'customer' => $customer,
            'orders' => Order::where('customer_id', $customer->id)->latest()->paginate(15),
            'count' => $count,
            'spent' => Money::cents($spent),
            'average' => Money::cents($count ? intdiv($spent, $count) : 0),
            'favorite' => \Illuminate\Support\Facades\DB::connection('tenant')->table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.customer_id', $customer->id)->where('orders.status', '!=', OrderStatus::Cancelled->value)
                ->selectRaw('order_items.name, sum(order_items.quantity) as qty')->groupBy('order_items.name')
                ->orderByDesc('qty')->limit(3)->get(),
        ])->title($customer->name);
    }
}
