{{-- Seletor de adicionais: folha que sobe de baixo no celular, janela no computador. Espera: $picking, $picked. --}}
@if ($picking)
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-stone-950/50 backdrop-blur-sm sm:items-center sm:p-4" wire:click.self="$set('pickingId', null)">
        <div class="relative flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-stone-100 px-5 pt-6 pb-4 sm:pt-4">
                <div>
                    <div class="absolute top-2 left-1/2 h-1.5 w-10 -translate-x-1/2 rounded-full bg-stone-200 sm:hidden"></div>
                    <h3 class="text-lg font-bold">{{ $picking->name }}</h3>
                    <p class="text-sm text-stone-500">{{ $picking->price_cents->format() }}</p>
                </div>
                <button type="button" wire:click="$set('pickingId', null)" class="btn-icon btn-ghost mt-2 rounded-full" aria-label="Fechar"><x-icon name="x" class="size-6" /></button>
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
                                        @class(['flex min-h-12 items-center justify-between gap-2 rounded-xl border-2 px-3 py-2 text-left text-sm transition', 'border-brand-600 bg-brand-50' => $on, 'border-stone-200 hover:border-stone-300' => ! $on])>
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

                <div>
                    <label class="label" for="pick-notes">Observação</label>
                    <input wire:model="pickNotes" id="pick-notes" type="text" class="input h-12" placeholder="Ex.: sem cebola">
                </div>
            </div>

            <div class="flex items-center gap-3 border-t border-stone-100 bg-stone-50/60 px-5 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))]" x-data="{ qty: $wire.entangle('pickQuantity') }">
                <div class="flex h-14 shrink-0 items-center rounded-2xl border border-stone-200 bg-white">
                    <button type="button" @click="qty = Math.max(1, qty - 1)" class="flex size-12 items-center justify-center text-stone-700" aria-label="Diminuir"><x-icon name="minus" class="size-5" stroke="2.4" /></button>
                    <span class="w-7 text-center text-lg font-bold tabular-nums" x-text="qty">{{ $pickQuantity }}</span>
                    <button type="button" @click="qty = Math.min(99, qty + 1)" class="flex size-12 items-center justify-center text-stone-700" aria-label="Aumentar"><x-icon name="plus" class="size-5" stroke="2.4" /></button>
                </div>
                <button type="button" wire:click="confirmPick" class="btn-primary h-14 min-w-0 flex-1 rounded-2xl px-3 text-base font-bold"><x-icon name="plus" class="size-5 max-[359px]:hidden" /> Adicionar</button>
            </div>
        </div>
    </div>
@endif
