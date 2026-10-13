<div class="space-y-6">
    <x-ui.page-header title="Bairros e taxas de entrega" subtitle="Cobre um valor único ou uma taxa por bairro. Por bairro, o cardápio só aceita entrega nos bairros listados." :back="route('delivery.board')" back-label="Entregas">
        <a href="{{ route('imports.index', ['tipo' => 'bairros']) }}" class="btn-secondary" wire:navigate><x-icon name="file" class="size-4" /> Importar planilha</a>
    </x-ui.page-header>

    <section class="card card-body space-y-3">
        <h2 class="section-title">Como cobrar a entrega</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <label @class(['flex cursor-pointer items-start gap-3 rounded-2xl border-2 p-4', 'border-brand-600 bg-brand-50' => $mode === 'fixed', 'border-stone-200' => $mode !== 'fixed'])>
                <input type="radio" wire:model.live="mode" value="fixed" class="mt-1 accent-brand-600">
                <span>
                    <span class="block font-semibold">Valor único</span>
                    <span class="text-sm text-stone-500">{{ $fixedFee->isZero() ? 'Entrega grátis' : $fixedFee->format() }} para qualquer endereço (altere em <a href="{{ route('settings.edit') }}" class="btn-link" wire:navigate>Configurações</a>).</span>
                </span>
            </label>
            <label @class(['flex cursor-pointer items-start gap-3 rounded-2xl border-2 p-4', 'border-brand-600 bg-brand-50' => $mode === 'zones', 'border-stone-200' => $mode !== 'zones'])>
                <input type="radio" wire:model.live="mode" value="zones" class="mt-1 accent-brand-600">
                <span>
                    <span class="block font-semibold">Por bairro</span>
                    <span class="text-sm text-stone-500">Taxa, pedido mínimo e tempo de cada bairro. Fora da lista, não entrega.</span>
                </span>
            </label>
        </div>
        @error('mode') <p class="field-error">{{ $message }}</p> @enderror
    </section>

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="section-title">Bairros atendidos ({{ $zones->where('is_active', true)->count() }})</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Bairro</th><th class="text-right">Taxa</th><th class="text-right">Mínimo</th><th class="text-right">Tempo</th><th class="text-right">Ações</th></tr></thead>
                    <tbody>
                        @forelse ($zones as $zone)
                            <tr wire:key="zone-{{ $zone->id }}" @class(['opacity-50' => ! $zone->is_active])>
                                <td class="font-semibold">{{ $zone->name }} @unless ($zone->is_active)<span class="badge-gray ml-1">pausado</span>@endunless</td>
                                <td class="text-right tabular-nums">{{ $zone->fee_cents->isZero() ? 'Grátis' : $zone->fee_cents->format() }}</td>
                                <td class="text-right tabular-nums text-stone-500">{{ $zone->min_order_cents?->format() ?? '—' }}</td>
                                <td class="text-right text-stone-500">{{ $zone->eta_minutes ? $zone->eta_minutes.' min' : '—' }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" wire:click="toggle({{ $zone->id }})" class="btn-ghost btn-sm">{{ $zone->is_active ? 'Pausar' : 'Ativar' }}</button>
                                    <button type="button" wire:click="edit({{ $zone->id }})" class="btn-icon btn-ghost size-8" title="Editar"><x-icon name="edit" class="size-4" /></button>
                                    <button type="button" wire:click="delete({{ $zone->id }})" wire:confirm="Excluir o bairro {{ $zone->name }}?" class="btn-icon btn-ghost size-8 text-red-600 hover:bg-red-50" title="Excluir"><x-icon name="trash" class="size-4" /></button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-ui.empty icon="pin" title="Nenhum bairro cadastrado" text="Cadastre os bairros que você atende e a taxa de cada um." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <form wire:submit="save" class="card card-body h-fit space-y-4 xl:sticky xl:top-6">
            <h2 class="section-title">{{ $editingId ? 'Editar bairro' : 'Novo bairro' }}</h2>
            <div>
                <label for="name" class="label">Bairro</label>
                <input wire:model="name" id="name" type="text" class="input" placeholder="Ex.: Centro">
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="fee" class="label">Taxa (R$)</label>
                    <input wire:model="fee" id="fee" type="text" inputmode="decimal" class="input" placeholder="5,00">
                    @error('fee') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="eta" class="label">Tempo (min)</label>
                    <input wire:model="eta" id="eta" type="number" class="input" placeholder="40">
                    @error('eta') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label for="minOrder" class="label">Pedido mínimo (opcional)</label>
                <input wire:model="minOrder" id="minOrder" type="text" inputmode="decimal" class="input" placeholder="Usa o mínimo geral">
                @error('minOrder') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="flex justify-end gap-2">
                @if ($editingId)<button type="button" wire:click="cancel" class="btn-secondary">Cancelar</button>@endif
                <button type="submit" class="btn-primary"><x-icon name="check" class="size-4" /> Salvar</button>
            </div>
        </form>
    </div>
</div>
