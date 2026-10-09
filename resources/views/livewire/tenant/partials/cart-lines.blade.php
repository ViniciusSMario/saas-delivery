{{-- Itens escolhidos ainda não enviados. Espera: $cart. --}}
<ul class="max-h-72 divide-y divide-stone-100 overflow-y-auto text-sm">
    @forelse ($cart as $line)
        <li wire:key="line-{{ $line['key'] }}" class="flex gap-2 py-2">
            <div class="min-w-0 flex-1">
                <p class="font-medium">{{ $line['name'] }}</p>
                @if ($line['addons'])<p class="text-xs text-stone-500">{{ implode(', ', array_column($line['addons'], 'name')) }}</p>@endif
                @if ($line['notes'])<p class="text-xs text-amber-700">Obs.: {{ $line['notes'] }}</p>@endif
            </div>
            <div class="flex items-center gap-1">
                <button type="button" wire:click="setQuantity('{{ $line['key'] }}', {{ $line['quantity'] - 1 }})" class="btn-secondary px-2 py-0.5" aria-label="Diminuir">−</button>
                <span class="w-6 text-center">{{ $line['quantity'] }}</span>
                <button type="button" wire:click="setQuantity('{{ $line['key'] }}', {{ $line['quantity'] + 1 }})" class="btn-secondary px-2 py-0.5" aria-label="Aumentar">+</button>
            </div>
            <p class="w-20 text-right whitespace-nowrap">{{ \App\Support\Money::cents(($line['unit'] + array_sum(array_column($line['addons'], 'price'))) * $line['quantity'])->format() }}</p>
        </li>
    @empty
        <li class="py-6 text-center text-stone-500">{{ $empty ?? 'Toque nos produtos para adicionar.' }}</li>
    @endforelse
</ul>
