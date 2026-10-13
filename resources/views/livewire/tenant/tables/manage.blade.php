<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">Cadastro de mesas</h1>
        <div class="flex gap-2">
            <a href="{{ route('tables.qr') }}" class="btn-secondary" wire:navigate><x-icon name="qr" class="size-4" /> QR codes</a>
            <a href="{{ route('tables.map') }}" class="btn-secondary" wire:navigate>Ver mapa</a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <form wire:submit="save" class="card card-body space-y-3">
            <h2 class="section-title">{{ $editingId ? 'Editar mesa' : 'Nova mesa' }}</h2>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label for="name" class="label">Número/nome</label>
                    <input wire:model="name" id="name" type="text" maxlength="20" class="input" placeholder="12">
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="seats" class="label">Lugares</label>
                    <input wire:model="seats" id="seats" type="number" min="1" max="99" class="input">
                </div>
                <div>
                    <label for="area" class="label">Área</label>
                    <input wire:model="area" id="area" type="text" maxlength="40" class="input" placeholder="Salão">
                </div>
            </div>
            @if ($editingId)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="checkbox"> Ativa</label>
            @endif
            <div class="flex gap-2">
                <button type="submit" class="btn-primary"><x-icon name="check" class="size-4" /> Salvar</button>
                @if ($editingId)<button type="button" wire:click="cancel" class="btn-secondary">Cancelar</button>@endif
            </div>
        </form>

        <form wire:submit="createRange" class="card card-body space-y-3">
            <h2 class="section-title">Criar várias</h2>
            <p class="text-sm text-stone-500">Usa os lugares e a área do formulário ao lado. Mesas que já existem são mantidas.</p>
            <div class="flex gap-2">
                <input wire:model="range" type="text" class="input max-w-40" placeholder="1-20" aria-label="Intervalo">
                <button type="submit" class="btn-secondary">Criar mesas</button>
            </div>
            @error('range') <p class="field-error">{{ $message }}</p> @enderror
        </form>
    </div>

    <div class="card overflow-x-auto">
        <table class="table table-cards">
            <thead><tr><th>Mesa</th><th>Lugares</th><th>Área</th><th>Comandas</th><th></th></tr></thead>
            <tbody>
                @forelse ($tables as $table)
                    <tr wire:key="tb-{{ $table->id }}">
                        <td class="font-medium">{{ $table->name }} @unless ($table->is_active)<span class="badge ml-1 bg-stone-200 text-stone-600">inativa</span>@endunless</td>
                        <td>{{ $table->seats }}</td>
                        <td>{{ $table->area ?? '—' }}</td>
                        <td>{{ $table->commands_count }}</td>
                        <td class="text-right"><button type="button" wire:click="edit({{ $table->id }})" class="btn-link">Editar</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-ui.empty icon="table" title="Nenhuma mesa ainda" text="Cadastre as mesas acima. Depois imprima os QR codes: o cliente pede pelo celular e o pedido cai na comanda." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
