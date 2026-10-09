{{-- Seletor de adicionais (modal). Espera: $picking, $picked. --}}
@if ($picking)
    <div class="fixed inset-0 z-40 flex items-end justify-center bg-stone-950/50 p-4 backdrop-blur-sm sm:items-center" wire:click.self="$set('pickingId', null)">
        <div class="card flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden shadow-2xl">
            <div class="card-header">
                <div>
                    <h3 class="text-lg font-bold">{{ $picking->name }}</h3>
                    <p class="text-sm text-stone-500">{{ $picking->price_cents->format() }}</p>
                </div>
                <button type="button" wire:click="$set('pickingId', null)" class="btn-icon btn-ghost rounded-full" aria-label="Fechar"><x-icon name="x" class="size-5" /></button>
            </div>

            <div class="flex-1 space-y-5 overflow-y-auto p-5">
                @error('picked') <div class="alert-error"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $message }}</div> @enderror

                @foreach ($picking->addonGroups as $group)
                    @continue($group->addons->isEmpty())
                    @php($count = count($picked[$group->id] ?? []))
                    <div>
                        <p class="flex items-center justify-between gap-2 text-sm font-semibold">
                            <span>{{ $group->name }} <span class="text-xs font-normal text-stone-500">{{ $group->rule() }}</span></span>
                            @if ($group->isRequired())
                                <span @class(['badge-green' => $count >= $group->min_choices, 'badge bg-stone-800 text-white' => $count < $group->min_choices])>{{ $count >= $group->min_choices ? 'OK' : 'obrigatório' }}</span>
                            @endif
                        </p>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            @foreach ($group->addons as $addon)
                                @php($on = in_array($addon->id, $picked[$group->id] ?? [], true))
                                <button type="button" wire:click="togglePick({{ $group->id }}, {{ $addon->id }})"
                                        @class(['flex items-center justify-between gap-2 rounded-xl border-2 px-3 py-2 text-left text-sm transition', 'border-brand-600 bg-brand-50' => $on, 'border-stone-200 hover:border-stone-300' => ! $on])>
                                    <span>
                                        <span class="block font-medium">{{ $addon->name }}</span>
                                        @unless ($addon->price_cents->isZero())<span class="block text-xs text-stone-500">+ {{ $addon->price_cents->format() }}</span>@endunless
                                    </span>
                                    @if ($on)<x-icon name="check" class="size-4 text-brand-600" stroke="3" />@endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="grid grid-cols-[6rem_1fr] gap-2">
                    <div>
                        <label class="label" for="pick-quantity">Qtd.</label>
                        <input wire:model="pickQuantity" id="pick-quantity" type="number" min="1" max="99" class="input">
                    </div>
                    <div>
                        <label class="label" for="pick-notes">Observação</label>
                        <input wire:model="pickNotes" id="pick-notes" type="text" class="input" placeholder="Ex.: sem cebola">
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-stone-100 bg-stone-50/60 px-5 py-4">
                <button type="button" wire:click="$set('pickingId', null)" class="btn-secondary">Cancelar</button>
                <button type="button" wire:click="confirmPick" class="btn-primary"><x-icon name="plus" class="size-4" /> Adicionar</button>
            </div>
        </div>
    </div>
@endif
