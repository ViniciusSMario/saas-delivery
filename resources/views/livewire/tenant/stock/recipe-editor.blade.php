<form wire:submit="save" class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('stock.recipes') }}" class="btn-link" wire:navigate>← Fichas técnicas</a>
            <h1 class="page-title mt-1">Ficha técnica · {{ $owner->name }}</h1>
            <p class="text-sm text-stone-500">{{ $ownerType === 'addon' ? 'Adicional: consumo extra por unidade do item.' : 'O que uma unidade do produto consome.' }}</p>
        </div>
        <button type="submit" class="btn-primary">Salvar ficha</button>
    </div>

    @if ($items->isEmpty())
        <div class="alert-error">Cadastre os insumos antes. <a href="{{ route('stock.index') }}" class="font-medium underline" wire:navigate>Ir para o estoque</a></div>
    @endif

    <section class="card card-body">
        <div class="space-y-2">
            @foreach ($lines as $i => $line)
                @php($item = $items->firstWhere('id', (int) $line['stock_item_id']))
                <div wire:key="line-{{ $i }}" class="grid grid-cols-[1fr_7rem_6rem_auto] items-start gap-2">
                    <select wire:model.live="lines.{{ $i }}.stock_item_id" class="input" aria-label="Insumo">
                        <option value="">Insumo</option>
                        @foreach ($items as $option)
                            <option value="{{ $option->id }}">{{ $option->name }} ({{ $option->unit->code }})</option>
                        @endforeach
                    </select>
                    <input wire:model.live.debounce.400ms="lines.{{ $i }}.quantity" type="text" inputmode="decimal" class="input text-right" placeholder="Qtd." aria-label="Quantidade">
                    <select wire:model.live="lines.{{ $i }}.unit_id" class="input" aria-label="Unidade">
                        @foreach ($units as $unit)
                            @if (! $item || $unit->compatibleWith($item->unit))
                                <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                            @endif
                        @endforeach
                    </select>
                    <button type="button" wire:click="removeLine({{ $i }})" class="btn-secondary h-9 px-3 text-red-700" aria-label="Remover">×</button>
                    @error("lines.$i") <p class="field-error col-span-4">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
        <button type="button" wire:click="addLine" class="btn-secondary mt-3">+ Insumo</button>
    </section>

    <section class="card card-body grid gap-4 text-sm sm:grid-cols-3">
        <div><p class="text-stone-500">Custo pela ficha</p><p class="text-lg font-semibold">{{ $cost->format() }}</p></div>
        <div><p class="text-stone-500">Preço de venda</p><p class="text-lg font-semibold">{{ $price->format() }}</p></div>
        <div><p class="text-stone-500">Margem</p><p class="text-lg font-semibold">{{ $margin !== null ? number_format($margin, 1, ',', '.').'%' : '—' }}</p></div>
        <p class="hint sm:col-span-3">Custo calculado pelo custo médio atual de cada insumo (atualizado a cada entrada).</p>
    </section>
</form>
