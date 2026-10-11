<?php

namespace App\Modules\Reports;

use App\Modules\Access\Models\User;
use App\Modules\Orders\Models\OrderReview;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Avaliações dos clientes no período: nota média, distribuição, motivos, entregadores e comentários. */
class ReviewsReport
{
    /** @return array{count: int, average: ?float, satisfied: ?float, distribution: array<int, int>} */
    public function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = OrderReview::whereBetween('created_at', [$from, $to])
            ->selectRaw('rating, count(*) as n')->groupBy('rating')->pluck('n', 'rating');
        $distribution = collect(range(5, 1))->mapWithKeys(fn ($r) => [$r => (int) ($counts[$r] ?? 0)])->all();
        $total = array_sum($distribution);

        return [
            'count' => $total,
            'average' => $total ? round(array_sum(array_map(fn ($r, $n) => $r * $n, array_keys($distribution), $distribution)) / $total, 1) : null,
            // "Satisfeitos" = 4 ou 5 estrelas.
            'satisfied' => $total ? round(($distribution[5] + $distribution[4]) / $total * 100) : null,
            'distribution' => $distribution,
        ];
    }

    /** Motivos mais marcados (o que deu errado e o que gostaram), do mais citado ao menos. */
    public function tags(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return DB::connection('tenant')->table('order_reviews')
            ->crossJoin(DB::raw('jsonb_array_elements_text(order_reviews.tags) as tag'))
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('tags')
            ->selectRaw('tag, count(*) as n')
            ->groupBy('tag')
            ->orderByDesc('n')
            ->get()
            ->map(fn ($row) => [
                'label' => OrderReview::tagLabel($row->tag),
                'count' => (int) $row->n,
                'negative' => array_key_exists($row->tag, OrderReview::NEGATIVE_TAGS),
            ]);
    }

    /** Nota média por entregador (avaliações de pedidos que ele entregou). */
    public function couriers(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $rows = OrderReview::whereBetween('created_at', [$from, $to])->whereNotNull('courier_id')
            ->selectRaw('courier_id, count(*) as n, avg(rating) as average, count(*) filter (where rating <= 2) as bad')
            ->groupBy('courier_id')->orderByDesc('average')->get();
        $names = User::whereIn('id', $rows->pluck('courier_id'))->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'name' => $names[$row->courier_id] ?? 'Usuário removido',
            'count' => (int) $row->n,
            'average' => round((float) $row->average, 1),
            'bad' => (int) $row->bad,
        ]);
    }

    /** Avaliações mais recentes (com comentário ou nota baixa primeiro aparecem na ordem do tempo). */
    public function latest(CarbonImmutable $from, CarbonImmutable $to, int $limit = 50): Collection
    {
        return OrderReview::whereBetween('created_at', [$from, $to])
            ->with(['order:id,number,customer_name,fulfillment', 'courier:id,name'])
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }
}
