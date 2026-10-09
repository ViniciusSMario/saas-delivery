<?php

namespace App\Livewire\Tenant\Customers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Clientes')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $sort = 'recent';

    public function updated($property): void
    {
        if (in_array($property, ['search', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        // Totais por cliente (pedidos não cancelados) numa subconsulta.
        $stats = DB::connection('tenant')->table('orders')
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, count(*) as orders_count, sum(total_cents) as spent_cents')
            ->groupBy('customer_id');

        $term = trim($this->search);
        $digits = Customer::normalizePhone($term);

        $customers = Customer::query()
            ->leftJoinSub($stats, 'stats', 'stats.customer_id', '=', 'customers.id')
            ->select('customers.*', DB::raw('coalesce(stats.orders_count, 0) as orders_count'), DB::raw('coalesce(stats.spent_cents, 0) as spent_cents'))
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('customers.name', 'ilike', '%'.$term.'%')
                ->when(strlen($digits) >= 4, fn ($q) => $q->orWhere('customers.phone', 'like', '%'.$digits.'%'))))
            ->when($this->sort === 'spent', fn ($q) => $q->orderByDesc('spent_cents'))
            ->when($this->sort === 'orders', fn ($q) => $q->orderByDesc('orders_count'))
            ->when($this->sort === 'name', fn ($q) => $q->orderBy('customers.name'))
            ->when($this->sort === 'recent', fn ($q) => $q->orderByRaw('customers.last_order_at desc nulls last'))
            ->paginate(30);

        return view('livewire.tenant.customers.index', ['customers' => $customers]);
    }
}
