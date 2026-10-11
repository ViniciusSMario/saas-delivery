<?php

namespace App\Modules\Reports;

use App\Modules\Access\Models\User;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderStatus;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tempos da operação, a partir do histórico de status de cada pedido:
 * - aceite: pedido online chegou → alguém aceitou (Em preparo);
 * - preparo: Em preparo → Pronto;
 * - entrega: saiu para entrega → entregue;
 * - total: pedido feito → concluído (entregue, retirado ou servido).
 * Intervalos acima de 4 horas ficam de fora (pedido esquecido aberto distorceria a média).
 */
class OperationsReport
{
    public const MAX_INTERVAL = '4 hours';

    public readonly string $timezone;

    public function __construct()
    {
        $this->timezone = Setting::read('timezone', 'America/Sao_Paulo');
    }

    /**
     * Tempos médios (em segundos) no geral e por tipo de pedido.
     *
     * @return array{all: array, by_fulfillment: Collection}
     */
    public function timings(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->metrics(DB::connection('tenant')->query()->fromSub($this->base($from, $to), 't'))
            ->addSelect('fulfillment')
            ->groupBy('fulfillment')
            ->get()
            ->keyBy('fulfillment');

        $all = $this->metrics(DB::connection('tenant')->query()->fromSub($this->base($from, $to), 't'))->first();

        return [
            'all' => $this->row($all),
            'by_fulfillment' => collect(Fulfillment::cases())
                ->filter(fn (Fulfillment $f) => isset($rows[$f->value]))
                ->map(fn (Fulfillment $f) => ['label' => $f->label(), 'fulfillment' => $f] + $this->row($rows[$f->value]))
                ->values(),
        ];
    }

    /** Pedidos e preparo médio por hora do dia: mostra se a cozinha atrasa no pico. */
    public function byHour(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::connection('tenant')->query()->fromSub($this->base($from, $to), 't')
            ->selectRaw("extract(hour from (created_at at time zone 'UTC') at time zone ?)::int as h, count(*) as n, ".$this->average('preparing_at', 'ready_at').' as prep', [$this->timezone])
            ->groupBy('h')
            ->get()
            ->keyBy('h');

        return collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => [
            'count' => (int) ($rows[$h]->n ?? 0),
            'prep' => isset($rows[$h]->prep) ? (float) $rows[$h]->prep : null,
        ]])->all();
    }

    /** Desempenho por entregador: entregas concluídas, taxas e tempos. */
    public function couriers(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $rows = DB::connection('tenant')->query()->fromSub($this->base($from, $to), 't')
            ->where('fulfillment', Fulfillment::Delivery->value)
            ->whereNotNull('courier_id')
            ->whereNotNull('completed_at')
            ->selectRaw(implode(', ', [
                'courier_id',
                'count(*) as deliveries',
                'coalesce(sum(delivery_fee_cents), 0) as fees',
                'coalesce(sum(total_cents), 0) as total',
                $this->average('dispatched_at', 'completed_at').' as road',
                $this->average('created_at', 'completed_at').' as door',
                "count(*) filter (where completed_at - dispatched_at > interval '45 minutes' and completed_at - dispatched_at < interval '".self::MAX_INTERVAL."') as slow",
            ]))
            ->groupBy('courier_id')
            ->orderByDesc('deliveries')
            ->get();

        $names = User::whereIn('id', $rows->pluck('courier_id'))->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'name' => $names[$row->courier_id] ?? 'Usuário removido',
            'deliveries' => (int) $row->deliveries,
            'fees' => Money::cents((int) $row->fees),
            'total' => Money::cents((int) $row->total),
            'road' => $row->road !== null ? (float) $row->road : null,
            'door' => $row->door !== null ? (float) $row->door : null,
            'slow' => (int) $row->slow,
        ]);
    }

    /** "18 min", "1h05" ou "—". */
    public static function duration(?float $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $minutes = (int) round($seconds / 60);

        return $minutes < 60 ? "{$minutes} min" : intdiv($minutes, 60).'h'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }

    /** Pedidos não cancelados do período, com a hora em que foram aceitos e ficaram prontos. */
    private function base(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $reached = fn (OrderStatus $status) => "(select min(h.created_at) from order_status_history h where h.order_id = orders.id and h.to_status = '{$status->value}')";

        return DB::connection('tenant')->table('orders')
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->whereBetween('created_at', [$from, $to])
            ->select(['id', 'source', 'fulfillment', 'created_at', 'dispatched_at', 'completed_at', 'courier_id', 'delivery_fee_cents', 'total_cents'])
            ->selectRaw($reached(OrderStatus::Preparing).' as preparing_at')
            ->selectRaw($reached(OrderStatus::Ready).' as ready_at');
    }

    private function metrics(Builder $query): Builder
    {
        return $query->selectRaw(implode(', ', [
            'count(*) as orders',
            $this->average('created_at', 'preparing_at')." filter (where source = 'online') as accept",
            $this->average('preparing_at', 'ready_at').' as prep',
            $this->average('dispatched_at', 'completed_at')." filter (where fulfillment = 'delivery') as road",
            $this->average('created_at', 'completed_at').' as door',
        ]));
    }

    /** Média em segundos de "fim − início", ignorando intervalos vazios, negativos ou longos demais. */
    private function average(string $start, string $end): string
    {
        return "avg(case when {$end} > {$start} and {$end} - {$start} < interval '".self::MAX_INTERVAL."' then extract(epoch from {$end} - {$start}) end)";
    }

    /** @return array{orders: int, accept: ?float, prep: ?float, road: ?float, door: ?float} */
    private function row(?object $row): array
    {
        $value = fn ($v) => $v !== null ? (float) $v : null;

        return [
            'orders' => (int) ($row->orders ?? 0),
            'accept' => $value($row->accept ?? null),
            'prep' => $value($row->prep ?? null),
            'road' => $value($row->road ?? null),
            'door' => $value($row->door ?? null),
        ];
    }
}
