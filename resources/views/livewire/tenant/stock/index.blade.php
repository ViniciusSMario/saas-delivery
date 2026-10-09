@php use App\Support\Quantity; @endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="page-title">Estoque</h1>
            <p class="mt-1 text-sm text-stone-500">Valor em estoque (custo médio): {{ $totalValue->format() }}{{ $lowCount ? ' · '.$lowCount.' abaixo do mínimo' : '' }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('stock.recipes') }}" class="btn-secondary" wire:navigate>Fichas técnicas</a>
            <a href="{{ route('stock.consumption') }}" class="btn-secondary" wire:navigate>Consumo</a>
        </div>
    </div>

    <form wire:submit="create" class="card card-body grid gap-3 sm:grid-cols-[1fr_8rem_8rem_auto] sm:items-end">
        <div>
            <label for="name" class="label">Novo insumo</label>
            <input wire:model="name" id="name" type="text" class="input" placeholder="Ex.: Muçarela">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="unitId" class="label">Unidade</label>
            <select wire:model="unitId" id="unitId" class="input">
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->code }} · {{ $unit->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="minQuantity" class="label">Mínimo</label>
            <input wire:model="minQuantity" id="minQuantity" type="text" inputmode="decimal" class="input">
            @error('minQuantity') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn-primary">Adicionar</button>
    </form>

    <div class="flex flex-wrap items-center gap-3">
        <input wire:model.live.debounce.300ms="search" type="search" class="input max-w-xs" placeholder="Buscar insumo" aria-label="Buscar insumo">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="onlyLow" class="checkbox"> Só abaixo do mínimo</label>
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead class="bg-stone-50">
                <tr><th>Insumo</th><th class="text-right">Saldo</th><th class="text-right">Mínimo</th><th class="text-right">Custo médio</th><th class="text-right">Valor</th><th class="text-right">Movimentar</th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($items as $item)
                    <tr wire:key="si-{{ $item->id }}">
                        <td>
                            <a href="{{ route('stock.show', $item) }}" class="font-medium text-stone-900 hover:underline" wire:navigate>{{ $item->name }}</a>
                            @if (Quantity::compare($item->quantity, '0') < 0)
                                <span class="badge ml-1 bg-red-100 text-red-700">negativo</span>
                            @elseif ($item->isBelowMinimum())
                                <span class="badge ml-1 bg-amber-100 text-amber-800">abaixo do mínimo</span>
                            @endif
                            @unless ($item->is_active)<span class="badge ml-1 bg-stone-200 text-stone-600">inativo</span>@endunless
                        </td>
                        <td class="text-right font-medium whitespace-nowrap">{{ Quantity::format($item->quantity) }} {{ $item->unit->code }}</td>
                        <td class="text-right whitespace-nowrap text-stone-500">{{ Quantity::format($item->min_quantity) }} {{ $item->unit->code }}</td>
                        <td class="text-right whitespace-nowrap text-stone-500">{{ $item->averageCost()->format() }}/{{ $item->unit->code }}</td>
                        <td class="text-right whitespace-nowrap">{{ $item->stockValue()->format() }}</td>
                        <td class="text-right whitespace-nowrap">
                            <button type="button" wire:click="startMovement({{ $item->id }}, 'purchase')" class="btn-link">Entrada</button>
                            <button type="button" wire:click="startMovement({{ $item->id }}, 'loss')" class="btn-link ml-2">Perda</button>
                            <button type="button" wire:click="startMovement({{ $item->id }}, 'count')" class="btn-link ml-2">Contagem</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-stone-500">Nenhum insumo. Cadastre acima (ex.: Muçarela em kg, Massa em kg, Caixa de pizza em un).</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($moving)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-stone-900/40 p-4 sm:items-center" wire:click.self="$set('movingId', null)">
            <form wire:submit="saveMovement" class="card w-full max-w-sm space-y-4 p-5">
                <div>
                    <h3 class="text-lg font-semibold">{{ $moving->name }}</h3>
                    <p class="text-sm text-stone-500">Saldo atual: {{ Quantity::format($moving->quantity) }} {{ $moving->unit->code }}</p>
                </div>
                <div class="grid grid-cols-3 gap-2 text-sm">
                    @foreach (['purchase' => 'Entrada', 'loss' => 'Perda', 'count' => 'Contagem'] as $value => $label)
                        <label @class(['cursor-pointer rounded-lg border px-2 py-1.5 text-center', 'border-brand-600 bg-brand-50 text-brand-700' => $movement === $value, 'border-stone-300' => $movement !== $value])>
                            <input type="radio" wire:model.live="movement" value="{{ $value }}" class="sr-only"> {{ $label }}
                        </label>
                    @endforeach
                </div>
                <div>
                    <label for="quantity" class="label">{{ $movement === 'count' ? 'Quantidade contada' : 'Quantidade' }} ({{ $moving->unit->code }})</label>
                    <input wire:model="quantity" id="quantity" type="text" inputmode="decimal" class="input" autofocus placeholder="Ex.: 2,5">
                    @error('quantity') <p class="field-error">{{ $message }}</p> @enderror
                    @if ($movement === 'count')<p class="hint">O sistema lança a diferença para o saldo atual como ajuste.</p>@endif
                </div>
                @if ($movement === 'purchase')
                    <div>
                        <label for="cost" class="label">Valor total pago (R$)</label>
                        <input wire:model="cost" id="cost" type="text" inputmode="decimal" class="input" placeholder="Ex.: 120,00">
                        @error('cost') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div>
                    <label for="reason" class="label">{{ $movement === 'loss' ? 'Motivo' : 'Observação (opcional)' }}</label>
                    <input wire:model="reason" id="reason" type="text" class="input" placeholder="{{ $movement === 'loss' ? 'Ex.: venceu, caiu no chão' : 'Ex.: NF 1234' }}">
                    @error('reason') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('movingId', null)" class="btn-secondary">Cancelar</button>
                    <button type="submit" class="btn-primary">Registrar</button>
                </div>
            </form>
        </div>
    @endif
</div>
