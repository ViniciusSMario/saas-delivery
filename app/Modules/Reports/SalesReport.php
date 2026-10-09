<?php

namespace App\Modules\Reports;

use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\MovementType;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PaymentMethod;
use App\Modules\Stock\Models\Recipe;
use App\Modules\Tables\CommandStatus;
use App\Modules\Tables\Models\Command;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Relatórios do estabelecimento, consultando o schema dele direto (sem agregados).
 *
 * "Venda" = pedido online/balcão não cancelado + conta de mesa fechada. Os lançamentos
 * de mesa não contam sozinhos: entram pelo total da comanda (com taxa e desconto).
 */
class SalesReport
{
    public readonly string $timezone;

    public function __construct()
    {
        $this->timezone = Setting::read('timezone', 'America/Sao_Paulo');
    }

    /** Início e fim de um dia (ou intervalo) no fuso do estabelecimento, em UTC para o banco. */
    public function range(string $from, string $to): array
    {
        return [
            CarbonImmutable::parse($from, $this->timezone)->startOfDay()->utc(),
            CarbonImmutable::parse($to, $this->timezone)->endOfDay()->utc(),
        ];
    }

    /** @return array{revenue: Money, count: int, average: Money, channels: array<string, array{count: int, total: Money}>, service_fee: Money, discounts: Money} */
    public function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $orders = $this->orders($from, $to)
            ->selectRaw('source, count(*) as n, coalesce(sum(total_cents), 0) as total, coalesce(sum(discount_cents), 0) as discounts')
            ->groupBy('source')
            ->get()
            ->keyBy(fn ($row) => $row->source->value);

        $commands = $this->commands($from, $to)
            ->selectRaw('count(*) as n, coalesce(sum(total_cents), 0) as total, coalesce(sum(service_fee_cents), 0) as fee, coalesce(sum(discount_cents), 0) as discounts')
            ->first();

        $channels = [
            'online' => ['label' => 'Cardápio online', 'count' => (int) ($orders['online']->n ?? 0), 'total' => Money::cents((int) ($orders['online']->total ?? 0))],
            'counter' => ['label' => 'Balcão / telefone', 'count' => (int) ($orders['counter']->n ?? 0), 'total' => Money::cents((int) ($orders['counter']->total ?? 0))],
            'table' => ['label' => 'Mesas', 'count' => (int) $commands->n, 'total' => Money::cents((int) $commands->total)],
        ];

        $revenue = array_reduce($channels, fn (Money $s, $c) => $s->add($c['total']), Money::zero());
        $count = array_sum(array_column($channels, 'count'));

        return [
            'revenue' => $revenue,
            'count' => $count,
            'average' => $count ? Money::cents(intdiv($revenue->cents, $count)) : Money::zero(),
            'channels' => $channels,
            'service_fee' => Money::cents((int) $commands->fee),
            'discounts' => Money::cents((int) $orders->sum('discounts') + (int) $commands->discounts),
        ];
    }

    /** Recebido por forma de pagamento (livro do caixa: vendas − estornos). */
    public function byPaymentMethod(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $rows = DB::connection('tenant')->table('cash_movements')
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('type', [MovementType::Sale->value, MovementType::Refund->value])
            ->selectRaw("payment_method_id, sum(case when type = 'sale' then amount_cents else -amount_cents end) as net")
            ->groupBy('payment_method_id')
            ->pluck('net', 'payment_method_id');

        return PaymentMethod::orderBy('position')->get()
            ->map(fn ($m) => ['method' => $m->name, 'total' => Money::cents((int) ($rows[$m->id] ?? 0))])
            ->filter(fn ($r) => ! $r['total']->isZero())
            ->values();
    }

    /** Vendas por dia. */
    public function daily(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $day = fn (string $column) => "to_char(({$column} at time zone 'UTC') at time zone ?, 'YYYY-MM-DD')";

        $orders = $this->orders($from, $to)
            ->selectRaw($day('created_at').' as day, count(*) as n, sum(total_cents) as total', [$this->timezone])
            ->groupBy('day')->get()->keyBy('day');
        $commands = $this->commands($from, $to)
            ->selectRaw($day('closed_at').' as day, count(*) as n, sum(total_cents) as total', [$this->timezone])
            ->groupBy('day')->get()->keyBy('day');

        $days = collect();
        for ($d = $from->setTimezone($this->timezone)->startOfDay(); $d <= $to; $d = $d->addDay()) {
            $key = $d->toDateString();
            $n = (int) ($orders[$key]->n ?? 0) + (int) ($commands[$key]->n ?? 0);
            $total = (int) ($orders[$key]->total ?? 0) + (int) ($commands[$key]->total ?? 0);
            $days->push(['date' => $d, 'count' => $n, 'total' => Money::cents($total), 'average' => Money::cents($n ? intdiv($total, $n) : 0)]);
        }

        return $days;
    }

    /** Produtos mais vendidos, com custo e margem pela ficha técnica atual. */
    public function topProducts(CarbonImmutable $from, CarbonImmutable $to, int $limit = 30): Collection
    {
        $rows = DB::connection('tenant')->table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw('order_items.product_id, order_items.combo_id, order_items.name, sum(order_items.quantity) as qty, sum(order_items.total_cents) as revenue')
            ->groupBy('order_items.product_id', 'order_items.combo_id', 'order_items.name')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get();

        $recipes = Recipe::with(['items.stockItem.unit', 'items.unit'])->whereIn('product_id', $rows->pluck('product_id')->filter())->get()->keyBy('product_id');

        return $rows->map(function ($row) use ($recipes) {
            $recipe = $row->product_id ? $recipes->get($row->product_id) : null;
            $cost = $recipe ? $recipe->cost()->multiply((int) $row->qty) : null;
            $revenue = Money::cents((int) $row->revenue);

            return [
                'name' => $row->name.($row->combo_id ? ' (combo)' : ''),
                'quantity' => (int) $row->qty,
                'revenue' => $revenue,
                'cost' => $cost,
                'margin' => $cost && $revenue->cents > 0 ? round(($revenue->cents - $cost->cents) / $revenue->cents * 100, 1) : null,
            ];
        });
    }

    /** Pedidos por hora do dia (horário de pico). */
    public function hourly(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Order::where('status', '!=', OrderStatus::Cancelled->value)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("extract(hour from (created_at at time zone 'UTC') at time zone ?)::int as h, count(*) as n", [$this->timezone])
            ->groupBy('h')
            ->pluck('n', 'h');

        return collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => (int) ($rows[$h] ?? 0)])->all();
    }

    /** Cancelamentos com motivo e quem cancelou. */
    public function cancellations(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Order::where('status', OrderStatus::Cancelled->value)
            ->whereBetween('cancelled_at', [$from, $to])
            ->with(['history' => fn ($q) => $q->where('to_status', OrderStatus::Cancelled->value)->with('user')])
            ->orderByDesc('cancelled_at')
            ->get();
    }

    /** Descontos dados (pedidos e contas) e taxas de serviço retiradas, por quem. */
    public function discounts(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $orders = $this->orders($from, $to)->where('discount_cents', '>', 0)->with('createdBy')->get()
            ->map(fn (Order $o) => ['when' => $o->created_at, 'what' => "Pedido #{$o->number}", 'amount' => $o->discount_cents, 'note' => null, 'by' => $o->createdBy?->name]);

        $commands = $this->commands($from, $to)
            ->where(fn ($q) => $q->where('discount_cents', '>', 0)->orWhere('service_fee_enabled', false))
            ->with(['diningTable'])->get()
            ->map(fn (Command $c) => [
                'when' => $c->closed_at,
                'what' => $c->displayName()." (#{$c->number})",
                'amount' => $c->discount_cents,
                'note' => $c->service_fee_enabled ? null : 'sem taxa de serviço',
                'by' => \App\Modules\Access\Models\User::find($c->closed_by)?->name,
            ]);

        return $orders->concat($commands)->sortByDesc('when')->values();
    }

    public function cashSessions(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return CashSession::where('status', 'closed')->whereBetween('closed_at', [$from, $to])
            ->with(['register', 'operator'])->latest('closed_at')->get();
    }

    private function orders(CarbonImmutable $from, CarbonImmutable $to)
    {
        return Order::where('source', '!=', OrderSource::Table->value)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->whereBetween('created_at', [$from, $to]);
    }

    private function commands(CarbonImmutable $from, CarbonImmutable $to)
    {
        return Command::where('status', CommandStatus::Closed->value)->whereBetween('closed_at', [$from, $to]);
    }
}
