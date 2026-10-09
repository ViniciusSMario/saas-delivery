@php use App\Support\Quantity; @endphp
<div class="space-y-6">
    <div>
        <a href="{{ route('stock.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-stone-500 hover:text-stone-800" wire:navigate><x-icon name="chevron-left" class="size-4" /> Estoque</a>
        <h1 class="page-title mt-1">Consumo de estoque</h1>
        <p class="text-sm text-stone-500">Custo do que saiu (vendas + perdas): {{ $totalCost->format() }} · só perdas: {{ $lossCost->format() }}</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <label class="text-sm">De <input wire:model.live="from" type="date" class="input inline-block w-auto"></label>
        <label class="text-sm">até <input wire:model.live="to" type="date" class="input inline-block w-auto"></label>
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Insumo</th><th class="text-right">Entradas</th><th class="text-right">Vendas</th><th class="text-right">Perdas</th><th class="text-right">Ajustes</th><th class="text-right">Custo saído</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    @php($u = $row['item']->unit->code)
                    <tr wire:key="c-{{ $row['item']->id }}">
                        <td><a href="{{ route('stock.show', $row['item']) }}" class="font-medium hover:underline" wire:navigate>{{ $row['item']->name }}</a></td>
                        <td class="text-right whitespace-nowrap">{{ Quantity::format($row['purchase']) }} {{ $u }}</td>
                        <td class="text-right whitespace-nowrap">{{ Quantity::format($row['sale']) }} {{ $u }}</td>
                        <td @class(['text-right whitespace-nowrap', 'text-red-700' => Quantity::isPositive($row['loss'])])>{{ Quantity::format($row['loss']) }} {{ $u }}</td>
                        <td class="text-right whitespace-nowrap">{{ Quantity::compare($row['adjustment'], '0') > 0 ? '+' : '' }}{{ Quantity::format($row['adjustment']) }} {{ $u }}</td>
                        <td class="text-right whitespace-nowrap">{{ $row['cost']->format() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-ui.empty icon="activity" title="Nenhuma movimentação no período" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
