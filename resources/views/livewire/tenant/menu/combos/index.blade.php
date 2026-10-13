<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="page-title">Combos</h1>
            <p class="mt-1 text-sm text-stone-500">Um combo só aparece no cardápio quando todos os produtos dele estão disponíveis.</p>
        </div>
        <a href="{{ route('menu.combos.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> Novo combo</a>
    </div>

    <div class="card overflow-x-auto">
        <table class="table table-cards">
            <thead>
                <tr><th>Combo</th><th class="text-right">Preço</th><th class="text-right">Avulso</th><th class="text-right">Ações</th></tr>
            </thead>
            <tbody>
                @forelse ($combos as $combo)
                    <tr wire:key="combo-{{ $combo->id }}">
                        <td>
                            <p class="font-medium text-stone-900">
                                {{ $combo->name }}
                                @unless ($combo->is_active)<span class="badge ml-1 bg-stone-200 text-stone-600">inativo</span>@endunless
                                @if ($combo->is_active && ! $combo->isAvailable())<span class="badge ml-1 bg-amber-100 text-amber-800">fora do cardápio: item indisponível</span>@endif
                            </p>
                            <p class="text-xs text-stone-500">{{ $combo->items->map(fn ($i) => $i->quantity.'× '.$i->product?->name)->implode(' · ') }}</p>
                        </td>
                        <td class="text-right whitespace-nowrap">{{ $combo->price_cents->format() }}</td>
                        <td class="text-right whitespace-nowrap text-stone-500">{{ $combo->regularPrice()->format() }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('menu.combos.edit', $combo) }}" class="btn-link" wire:navigate>Editar</a>
                            <button type="button" wire:click="delete({{ $combo->id }})" wire:confirm="Excluir o combo {{ $combo->name }}?" class="btn-link ml-3 text-red-700">Excluir</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">
                        <x-ui.empty icon="gift" title="Nenhum combo ainda" text="Junte produtos com um preço especial (ex.: pizza grande + refrigerante 2 L). O combo aparece em destaque no cardápio.">
                            <a href="{{ route('menu.combos.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> Novo combo</a>
                        </x-ui.empty>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
