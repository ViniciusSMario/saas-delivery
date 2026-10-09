@php use App\Support\Quantity; @endphp
<div class="space-y-6">
    <x-ui.page-header title="Estoque" subtitle="Insumos, saldos e movimentações.">
        <a href="{{ route('stock.recipes') }}" class="btn-secondary" wire:navigate><x-icon name="file" class="size-4" /> Fichas técnicas</a>
        <a href="{{ route('stock.consumption') }}" class="btn-secondary" wire:navigate><x-icon name="activity" class="size-4" /> Consumo</a>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.stat label="Valor em estoque (custo médio)" :value="$totalValue->format()" icon="box" tone="blue" />
        <x-ui.stat label="Abaixo do mínimo" :value="$lowCount" icon="alert" :tone="$lowCount ? 'amber' : 'green'" :hint="$lowCount ? 'Reponha estes insumos' : 'Tudo em dia'" />
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
        <button type="submit" class="btn-primary"><x-icon name="plus" class="size-4" /> Adicionar</button>
    </form>

    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 border-b border-stone-100 p-3">
            <label class="relative min-w-56 flex-1 sm:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-400" />
                <input wire:model.live.debounce.300ms="search" type="search" class="input pl-9" placeholder="Buscar insumo" aria-label="Buscar insumo">
            </label>
            <label class="flex items-center gap-2 text-sm font-medium text-stone-600"><input type="checkbox" wire:model.live="onlyLow" class="checkbox"> Só abaixo do mínimo</label>
        </div>
        <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr><th>Insumo</th><th class="text-right">Saldo</th><th class="text-right">Mínimo</th><th class="text-right">Custo médio</th><th class="text-right">Valor</th><th class="text-right">Movimentar</th></tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr wire:key="si-{{ $item->id }}">
                        <td>
                            <a href="{{ route('stock.show', $item) }}" class="font-semibold text-stone-900 hover:text-brand-700" wire:navigate>{{ $item->name }}</a>
                            @if (Quantity::compare($item->quantity, '0') < 0)
                                <span class="badge-red ml-1">negativo</span>
                            @elseif ($item->isBelowMinimum())
                                <span class="badge-amber ml-1">abaixo do mínimo</span>
                            @endif
                            @unless ($item->is_active)<span class="badge-gray ml-1">inativo</span>@endunless
                        </td>
                        <td class="text-right font-medium whitespace-nowrap">{{ Quantity::format($item->quantity) }} {{ $item->unit->code }}</td>
                        <td class="text-right whitespace-nowrap text-stone-500">{{ Quantity::format($item->min_quantity) }} {{ $item->unit->code }}</td>
                        <td class="text-right whitespace-nowrap text-stone-500">{{ $item->averageCost()->format() }}/{{ $item->unit->code }}</td>
                        <td class="text-right whitespace-nowrap">{{ $item->stockValue()->format() }}</td>
                        <td class="text-right whitespace-nowrap">
                            <div class="inline-flex overflow-hidden rounded-lg border border-stone-200 text-xs font-semibold">
                                <button type="button" wire:click="startMovement({{ $item->id }}, 'purchase')" class="px-2.5 py-1.5 text-emerald-700 hover:bg-emerald-50">Entrada</button>
                                <button type="button" wire:click="startMovement({{ $item->id }}, 'loss')" class="border-l border-stone-200 px-2.5 py-1.5 text-red-600 hover:bg-red-50">Perda</button>
                                <button type="button" wire:click="startMovement({{ $item->id }}, 'count')" class="border-l border-stone-200 px-2.5 py-1.5 text-stone-700 hover:bg-stone-50">Contagem</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-ui.empty icon="box" title="Nenhum insumo. Cadastre acima (ex.: Muçarela em kg, Massa em kg, Caixa de pizza em un)" /></td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    @if ($moving)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-stone-950/50 p-4 backdrop-blur-sm sm:items-center" wire:click.self="$set('movingId', null)">
            <form wire:submit="saveMovement" class="card w-full max-w-sm space-y-4 p-6 shadow-2xl">
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
                    @can('finance.manage')
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="createPayable" class="checkbox"> Gerar conta a pagar no financeiro</label>
                        @if ($createPayable)
                            <div class="grid grid-cols-2 gap-2">
                                <input wire:model="supplier" type="text" class="input" placeholder="Fornecedor" aria-label="Fornecedor">
                                <input wire:model="dueDate" type="date" class="input" aria-label="Vencimento">
                            </div>
                        @endif
                        @error('createPayable') <p class="field-error">{{ $message }}</p> @enderror
                    @endcan
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
