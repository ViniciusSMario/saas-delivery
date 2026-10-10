{{-- Itens escolhidos ainda não enviados. Espera: $cart. --}}
<ul class="-mx-1 divide-y sm:max-h-80 divide-stone-100 overflow-y-auto px-1 text-sm">
    @forelse ($cart as $line)
        <li wire:key="line-{{ $line['key'] }}" class="flex items-center gap-3 py-2.5">
            <div class="min-w-0 flex-1">
                <p class="font-semibold">{{ $line['name'] }}</p>
                @if ($line['addons'])<p class="text-xs text-stone-500">{{ implode(', ', array_column($line['addons'], 'name')) }}</p>@endif
                @if ($line['notes'])<p class="text-xs font-medium text-amber-700">Obs.: {{ $line['notes'] }}</p>@endif
                <p class="mt-0.5 font-bold tabular-nums">{{ \App\Support\Money::cents(($line['unit'] + array_sum(array_column($line['addons'], 'price'))) * $line['quantity'])->format() }}</p>
            </div>
            <div class="flex h-11 items-center rounded-xl border border-stone-200 text-brand-600 sm:h-8 sm:rounded-lg">
                <button type="button" wire:click="setQuantity('{{ $line['key'] }}', {{ $line['quantity'] - 1 }})" class="flex size-11 items-center justify-center sm:size-8" aria-label="{{ $line['quantity'] === 1 ? 'Remover' : 'Diminuir' }}">
                    <x-icon :name="$line['quantity'] === 1 ? 'trash' : 'minus'" class="size-4 sm:size-3.5" stroke="2.2" />
                </button>
                <span class="w-6 text-center font-bold text-stone-900">{{ $line['quantity'] }}</span>
                <button type="button" wire:click="setQuantity('{{ $line['key'] }}', {{ $line['quantity'] + 1 }})" class="flex size-11 items-center justify-center sm:size-8" aria-label="Aumentar">
                    <x-icon name="plus" class="size-4 sm:size-3.5" stroke="2.2" />
                </button>
            </div>
        </li>
    @empty
        <li class="flex flex-col items-center gap-2 py-8 text-center text-stone-500">
            <span class="flex size-11 items-center justify-center rounded-2xl bg-stone-100 text-stone-400"><x-icon name="bag" class="size-5" /></span>
            {{ $empty ?? 'Toque nos produtos para adicionar.' }}
        </li>
    @endforelse
</ul>
