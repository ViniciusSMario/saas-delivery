<?php

namespace App\Livewire\Tenant\Orders;

use App\Modules\Customers\Models\Customer;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Pedidos')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $date = '';

    public function mount(): void
    {
        $this->date = $this->date ?: CarbonImmutable::now($this->timezone())->toDateString();
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'date'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
    }

    private function timezone(): string
    {
        return Setting::read('timezone', 'America/Sao_Paulo');
    }

    public function render()
    {
        $tz = $this->timezone();
        $day = rescue(fn () => CarbonImmutable::parse($this->date, $tz), CarbonImmutable::now($tz), false);
        $range = [$day->startOfDay()->utc(), $day->endOfDay()->utc()];

        $query = Order::query()
            ->whereBetween('created_at', $range)
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when(trim($this->search) !== '', function ($q) {
                $term = trim($this->search);
                $digits = Customer::normalizePhone($term);
                $q->where(fn ($q) => $q
                    ->when(ctype_digit(ltrim($term, '#')), fn ($q) => $q->orWhere('number', (int) ltrim($term, '#')))
                    ->orWhere('customer_name', 'ilike', '%'.$term.'%')
                    ->when(strlen($digits) >= 4, fn ($q) => $q->orWhere('customer_phone', 'like', '%'.$digits.'%')));
            });

        $summary = (clone $query)->where('status', '!=', OrderStatus::Cancelled->value)
            ->selectRaw('count(*) as orders, coalesce(sum(total_cents), 0) as total')
            ->first();

        return view('livewire.tenant.orders.index', [
            'orders' => $query->with('payments.method')->latest()->paginate(30),
            'statuses' => OrderStatus::cases(),
            'summaryCount' => (int) $summary->orders,
            'summaryTotal' => Money::cents((int) $summary->total),
            'timezone' => $tz,
            'today' => CarbonImmutable::now($tz)->toDateString(),
        ]);
    }
}
