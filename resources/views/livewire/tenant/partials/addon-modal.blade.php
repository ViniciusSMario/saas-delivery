{{-- Seletor de adicionais (modal). Espera: $picking, $picked. --}}
@if ($picking)
    <div class="fixed inset-0 z-40 flex items-end justify-center bg-stone-900/40 p-4 sm:items-center" wire:click.self="$set('pickingId', null)">
        <div class="card max-h-[90vh] w-full max-w-md overflow-y-auto p-5">
            <h3 class="text-lg font-semibold">{{ $picking->name }}</h3>
            <p class="text-sm text-stone-500">{{ $picking->price_cents->format() }}</p>
            @error('picked') <div class="alert-error mt-3">{{ $message }}</div> @enderror

            @foreach ($picking->addonGroups as $group)
                @continue($group->addons->isEmpty())
                <div class="mt-4">
                    <p class="text-sm font-semibold">{{ $group->name }} <span class="text-xs font-normal text-stone-500">{{ $group->rule() }}{{ $group->isRequired() ? ' · obrigatório' : '' }}</span></p>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        @foreach ($group->addons as $addon)
                            @php($on = in_array($addon->id, $picked[$group->id] ?? [], true))
                            <button type="button" wire:click="togglePick({{ $group->id }}, {{ $addon->id }})"
                                    @class(['rounded-lg border px-2 py-1.5 text-left text-sm', 'border-brand-600 bg-brand-50' => $on, 'border-stone-300' => ! $on])>
                                {{ $addon->name }}
                                @unless ($addon->price_cents->isZero())<span class="block text-xs text-stone-500">+ {{ $addon->price_cents->format() }}</span>@endunless
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="mt-4 grid grid-cols-[6rem_1fr] gap-2">
                <input wire:model="pickQuantity" type="number" min="1" max="99" class="input" aria-label="Quantidade">
                <input wire:model="pickNotes" type="text" class="input" placeholder="Observação (ex.: sem cebola)" aria-label="Observação do item">
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="$set('pickingId', null)" class="btn-secondary">Cancelar</button>
                <button type="button" wire:click="confirmPick" class="btn-primary">Adicionar</button>
            </div>
        </div>
    </div>
@endif
