<?php

namespace App\Livewire\Tenant\Reports;

use App\Modules\Reports\OperationsReport;
use App\Modules\Reports\SalesReport;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Relatórios')]
class Index extends Component
{
    public const TABS = [
        'sales' => 'Vendas',
        'products' => 'Produtos',
        'hours' => 'Horários',
        'operation' => 'Tempos e entregas',
        'losses' => 'Cancelamentos e descontos',
        'cash' => 'Caixas',
    ];

    #[Url]
    public string $tab = 'sales';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(SalesReport $report): void
    {
        $now = CarbonImmutable::now($report->timezone);
        $this->from = $this->from ?: $now->subDays(6)->toDateString();
        $this->to = $this->to ?: $now->toDateString();
        $this->tab = array_key_exists($this->tab, self::TABS) ? $this->tab : 'sales';
    }

    public function preset(string $period): void
    {
        $now = CarbonImmutable::now(app(SalesReport::class)->timezone);

        [$this->from, $this->to] = match ($period) {
            'today' => [$now->toDateString(), $now->toDateString()],
            'week' => [$now->subDays(6)->toDateString(), $now->toDateString()],
            'month' => [$now->startOfMonth()->toDateString(), $now->toDateString()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth()->toDateString(), $now->subMonthNoOverflow()->endOfMonth()->toDateString()],
            default => [$this->from, $this->to],
        };
    }

    public function render()
    {
        $report = app(SalesReport::class);
        $operations = app(OperationsReport::class);
        $valid = fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        $from = $valid($this->from) ? $this->from : now()->toDateString();
        $to = $valid($this->to) && $this->to >= $from ? $this->to : $from;
        [$start, $end] = $report->range($from, $to);

        $data = ['summary' => $report->summary($start, $end)];

        match ($this->tab) {
            'sales' => $data += ['daily' => $report->daily($start, $end), 'methods' => $report->byPaymentMethod($start, $end)],
            'products' => $data += ['products' => $report->topProducts($start, $end)],
            'hours' => $data += [
                'hours' => $report->hourly($start, $end),
                'revenue' => $report->hourlyRevenue($start, $end),
                'heatmap' => $report->weekdayHours($start, $end),
            ],
            'operation' => $data += [
                'timings' => $operations->timings($start, $end),
                'prepByHour' => $operations->byHour($start, $end),
                'couriers' => $operations->couriers($start, $end),
            ],
            'losses' => $data += ['cancellations' => $report->cancellations($start, $end), 'discounts' => $report->discounts($start, $end)],
            'cash' => $data += ['sessions' => $report->cashSessions($start, $end)],
        };

        return view('livewire.tenant.reports.index', $data + [
            'tabs' => self::TABS,
            'timezone' => $report->timezone,
            'query' => ['from' => $from, 'to' => $to],
        ]);
    }
}
