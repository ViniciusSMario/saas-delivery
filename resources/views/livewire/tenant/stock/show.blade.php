@php
    use App\Support\Quantity;
    $tz = \App\Modules\Establishment\Models\Setting::read('timezone', 'America/Sao_Paulo');
@endphp
<div class="space-y-6">
    <div>
        <a href="{{ route('stock.index') }}" class="btn-link" wire:navigate>← Estoque</a>
        <h1 class="page-title mt-1">{{ $item->name }}</h1>
        <p class="text-sm text-stone-500">Saldo {{ Quantity::format($item->quantity) }} {{ $item->unit->code }} · custo médio {{ $item->averageCost()->format() }}/{{ $item->unit->code }} · valor {{ $item->stockValue()->format() }}</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="save" class="card card-body space-y-3">
            <h2 class="font-semibold">Dados</h2>
            <div>
                <label for="name" class="label">Nome</label>
                <input wire:model="name" id="name" type="text" class="input">
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="minQuantity" class="label">Estoque mínimo ({{ $item->unit->code }})</label>
                <input wire:model="minQuantity" id="minQuantity" type="text" inputmode="decimal" class="input">
                @error('minQuantity') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="isActive" class="checkbox"> Ativo</label>
            <p class="hint">A unidade não muda depois do cadastro (o histórico depende dela).</p>
            <button type="submit" class="btn-primary">Salvar</button>
        </form>

        <section class="card card-body lg:col-span-2">
            <h2 class="font-semibold">Usado em</h2>
            <p class="mt-2 text-sm text-stone-600">
                @forelse ($usedIn as $recipe)
                    @if ($recipe->product)
                        <a href="{{ route('stock.recipe.product', $recipe->product_id) }}" class="btn-link" wire:navigate>{{ $recipe->product->name }}</a>{{ $loop->last ? '' : ',' }}
                    @elseif ($recipe->addon)
                        <a href="{{ route('stock.recipe.addon', $recipe->addon_id) }}" class="btn-link" wire:navigate>{{ $recipe->addon->name }} (adicional)</a>{{ $loop->last ? '' : ',' }}
                    @endif
                @empty
                    Nenhuma ficha técnica usa este insumo.
                @endforelse
            </p>
        </section>
    </div>

    <section class="card overflow-x-auto">
        <h2 class="px-5 pt-5 font-semibold">Movimentações</h2>
        <table class="table mt-3">
            <thead class="bg-stone-50"><tr><th>Quando</th><th>Tipo</th><th>Origem</th><th>Por</th><th class="text-right">Quantidade</th><th class="text-right">Saldo</th></tr></thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($movements as $movement)
                    <tr wire:key="m-{{ $movement->id }}">
                        <td class="whitespace-nowrap text-stone-500">{{ $movement->created_at->timezone($tz)->format('d/m H:i') }}</td>
                        <td>{{ $movement->type->label() }}</td>
                        <td class="text-stone-600">
                            {{ $movement->reason }}
                            @if ($movement->unit_cost_cents) · {{ \App\Support\Money::cents($movement->unit_cost_cents)->format() }}/{{ $item->unit->code }} @endif
                        </td>
                        <td class="text-stone-500">{{ $movement->user?->name ?? 'Sistema' }}</td>
                        <td @class(['text-right whitespace-nowrap font-medium', 'text-red-700' => Quantity::compare($movement->quantity, '0') < 0, 'text-green-700' => Quantity::compare($movement->quantity, '0') > 0])>
                            {{ Quantity::compare($movement->quantity, '0') > 0 ? '+' : '' }}{{ Quantity::format($movement->quantity) }}
                        </td>
                        <td class="text-right whitespace-nowrap">{{ Quantity::format($movement->balance_after) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-stone-500">Nenhuma movimentação.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    {{ $movements->links() }}
</div>
