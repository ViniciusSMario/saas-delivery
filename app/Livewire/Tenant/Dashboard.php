<?php

namespace App\Livewire\Tenant;

use App\Modules\Establishment\Models\Establishment;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Orders\Models\Order;
use App\Modules\Reports\SalesReport;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Tables\Models\Command;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Início: o dia do estabelecimento. Cada bloco aparece conforme a permissão. */
#[Title('Início')]
class Dashboard extends Component
{
    /** Garçom e entregador vão direto para a tela deles. */
    public function mount(): void
    {
        $user = auth()->user();

        if ($user->usesFieldMode()) {
            $this->redirectRoute($user->fieldHome(), navigate: true);
        }
    }

    public function render()
    {
        $user = auth()->user();
        $report = app(SalesReport::class);
        $now = CarbonImmutable::now($report->timezone);
        $data = [
            'establishment' => Establishment::current(),
            'today' => OpeningHour::where('weekday', $now->dayOfWeek)->first(),
            'now' => $now,
            'lowStock' => collect(),
            'sales' => null,
        ];

        if ($user->can('reports.view')) {
            // Hoje até agora x mesmo dia da semana passada até o mesmo horário.
            $data['sales'] = $report->summary($now->startOfDay()->utc(), $now->utc());
            $data['lastWeek'] = $report->summary($now->subWeek()->startOfDay()->utc(), $now->subWeek()->utc());
            $data['methods'] = $report->byPaymentMethod($now->startOfDay()->utc(), $now->utc());
        }

        if ($user->can('orders.view')) {
            $data['openOrders'] = Order::open()->count();
        }

        if ($user->can('tables.manage')) {
            $data['openTables'] = Command::open()->whereNotNull('table_id')->count();
        }

        if ($user->can('finance.view')) {
            $today = $now->toDateString();
            $data['dueToday'] = Money::cents((int) FinancialEntry::open()->where('kind', 'payable')->where('due_date', $today)->sum('amount_cents'));
            $data['overdue'] = Money::cents((int) FinancialEntry::open()->where('kind', 'payable')->where('due_date', '<', $today)->sum('amount_cents'));
        }

        if ($user->can('stock.manage')) {
            $data['lowStock'] = StockItem::belowMinimum()->with('unit')->orderBy('name')->limit(8)->get();
        }

        return view('livewire.tenant.dashboard', $data);
    }
}
