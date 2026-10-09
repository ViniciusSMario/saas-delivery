<?php

namespace App\Livewire\Tenant\Stock;

use App\Modules\Establishment\Models\Setting;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockMovement;
use App\Support\Money;
use App\Support\Quantity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Consumo por período: quanto entrou e saiu de cada insumo, por tipo. */
#[Title('Consumo de estoque')]
class Consumption extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $now = CarbonImmutable::now(Setting::read('timezone', 'America/Sao_Paulo'));
        $this->from = $this->from ?: $now->subDays(6)->toDateString();
        $this->to = $this->to ?: $now->toDateString();
    }

    public function render()
    {
        $tz = Setting::read('timezone', 'America/Sao_Paulo');
        $from = rescue(fn () => CarbonImmutable::parse($this->from, $tz)->startOfDay(), CarbonImmutable::now($tz)->subDays(6)->startOfDay(), false);
        $to = rescue(fn () => CarbonImmutable::parse($this->to, $tz)->endOfDay(), CarbonImmutable::now($tz)->endOfDay(), false);

        $sums = StockMovement::query()
            ->whereBetween('created_at', [$from->utc(), $to->utc()])
            ->select('stock_item_id', 'type', DB::raw('sum(quantity) as total'))
            ->groupBy('stock_item_id', 'type')
            ->get()
            ->groupBy('stock_item_id');

        $items = StockItem::with('unit')->whereKey($sums->keys())->orderBy('name')->get()->map(function (StockItem $item) use ($sums) {
            $byType = $sums->get($item->id)->mapWithKeys(fn ($row) => [$row->type->value => (string) $row->total]);
            $out = Quantity::negate(Quantity::add($byType['sale'] ?? '0', $byType['loss'] ?? '0'));

            return [
                'item' => $item,
                'purchase' => $byType['purchase'] ?? '0',
                'sale' => Quantity::negate($byType['sale'] ?? '0'),
                'loss' => Quantity::negate($byType['loss'] ?? '0'),
                'adjustment' => $byType['adjustment'] ?? '0',
                'cost' => Money::cents((int) Quantity::round(Quantity::mul($out, (string) $item->average_cost_cents, 6))),
            ];
        });

        return view('livewire.tenant.stock.consumption', [
            'rows' => $items,
            'totalCost' => $items->reduce(fn (Money $s, $r) => $s->add($r['cost']), Money::zero()),
            'lossCost' => $items->reduce(fn (Money $s, $r) => $s->add(Money::cents((int) Quantity::round(Quantity::mul($r['loss'], (string) $r['item']->average_cost_cents, 6)))), Money::zero()),
        ]);
    }
}
