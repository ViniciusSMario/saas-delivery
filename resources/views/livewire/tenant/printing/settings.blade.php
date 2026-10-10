<div class="space-y-6">
    <x-ui.page-header title="Impressoras" subtitle="Cada setor (cozinha, bar, expedição) recebe só os itens das suas categorias.">
        <a href="{{ route('printing.station') }}" class="btn-secondary" wire:navigate><x-icon name="printer" class="size-4" /> Abrir estação de impressão</a>
    </x-ui.page-header>

    <div class="grid gap-6 xl:grid-cols-[1fr_24rem]">
        <div class="space-y-6">
            {{-- Setores --}}
            <section class="card overflow-hidden">
                <div class="card-header"><h2 class="section-title">Setores e impressoras</h2></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Setor</th><th>Impressão</th><th>Papel</th><th class="text-right">Ações</th></tr></thead>
                        <tbody>
                            @forelse ($stations as $station)
                                <tr wire:key="st-{{ $station->id }}">
                                    <td>
                                        <p class="font-semibold">{{ $station->name }}</p>
                                        <p class="mt-0.5 flex flex-wrap gap-1">
                                            @if ($station->is_default)<span class="badge-brand">padrão</span>@endif
                                            @if ($station->prints_full_order)<span class="badge-blue">pedido completo</span>@endif
                                            @unless ($station->is_active)<span class="badge-gray">inativo</span>@endunless
                                            <span class="badge-gray">{{ $station->categories_count }} categoria(s)</span>
                                        </p>
                                    </td>
                                    <td class="text-stone-600">
                                        {{ $station->isNetwork() ? 'Rede · '.$station->host.':'.$station->port : 'Estação no navegador' }}
                                        @if ($station->pending_count)<span class="badge-amber ml-1">{{ $station->pending_count }} na fila</span>@endif
                                    </td>
                                    <td class="whitespace-nowrap text-stone-600">{{ $station->columns === 32 ? '58 mm' : '80 mm' }}{{ $station->copies > 1 ? ' · '.$station->copies.' vias' : '' }}</td>
                                    <td class="text-right whitespace-nowrap">
                                        <button type="button" wire:click="test({{ $station->id }})" class="btn-ghost btn-sm"><x-icon name="printer" class="size-4" /> Testar</button>
                                        <button type="button" wire:click="edit({{ $station->id }})" class="btn-icon btn-ghost size-8" title="Editar"><x-icon name="edit" class="size-4" /></button>
                                        <button type="button" wire:click="delete({{ $station->id }})" wire:confirm="Excluir o setor {{ $station->name }}? As categorias dele passam para o setor padrão." class="btn-icon btn-ghost size-8 text-red-600 hover:bg-red-50" title="Excluir"><x-icon name="trash" class="size-4" /></button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><x-ui.empty icon="printer" title="Nenhum setor ainda" text="Comece pela cozinha. Depois, se tiver, o bar e a expedição (pedido completo, para conferir e embalar)." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Categorias → setores --}}
            @if ($stations->isNotEmpty())
                <section class="card overflow-hidden">
                    <div class="card-header">
                        <div>
                            <h2 class="section-title">Onde sai cada categoria</h2>
                            <p class="text-xs text-stone-500">Ex.: Bebidas no bar, Pizzas na cozinha. Sem setor = setor padrão.</p>
                        </div>
                    </div>
                    <ul class="divide-y divide-stone-100">
                        @forelse ($categories as $category)
                            <li class="flex items-center justify-between gap-3 px-5 py-2.5" wire:key="cat-{{ $category->id }}">
                                <span class="font-medium">{{ $category->name }}</span>
                                <select wire:model.live="routing.{{ $category->id }}" class="input w-auto" aria-label="Setor de {{ $category->name }}">
                                    <option value="">Setor padrão</option>
                                    @foreach ($stations->where('prints_full_order', false) as $station)
                                        <option value="{{ $station->id }}">{{ $station->name }}</option>
                                    @endforeach
                                </select>
                            </li>
                        @empty
                            <li><x-ui.empty icon="tag" title="Nenhuma categoria no cardápio" /></li>
                        @endforelse
                    </ul>
                </section>
            @endif

            @if ($failed->isNotEmpty())
                <section class="alert-error">
                    <x-icon name="alert" class="mt-0.5 size-4" />
                    <div>
                        <p class="font-semibold">Cupons que não saíram</p>
                        <ul class="mt-1 font-normal">
                            @foreach ($failed as $job)
                                <li>{{ $job->title }} · {{ $job->station->name }}: {{ $job->error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endif
        </div>

        {{-- Formulário --}}
        <form wire:submit="save" class="card card-body h-fit space-y-4 xl:sticky xl:top-6">
            <h2 class="section-title">{{ $editingId ? 'Editar setor' : 'Novo setor' }}</h2>
            <div>
                <label for="name" class="label">Nome</label>
                <input wire:model="name" id="name" type="text" class="input" placeholder="Ex.: Cozinha, Bar, Expedição">
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Como imprime</label>
                <div class="grid gap-2">
                    <label @class(['flex cursor-pointer items-start gap-3 rounded-xl border-2 p-3', 'border-brand-600 bg-brand-50' => $mode === 'browser', 'border-stone-200' => $mode !== 'browser'])>
                        <input type="radio" wire:model.live="mode" value="browser" class="mt-1 accent-brand-600">
                        <span><span class="block text-sm font-semibold">Estação no navegador</span><span class="text-xs text-stone-500">Um computador com a impressora deixa a “estação de impressão” aberta. Funciona com qualquer impressora.</span></span>
                    </label>
                    <label @class(['flex items-start gap-3 rounded-xl border-2 p-3', 'cursor-pointer' => $networkEnabled, 'opacity-60' => ! $networkEnabled, 'border-brand-600 bg-brand-50' => $mode === 'network', 'border-stone-200' => $mode !== 'network'])>
                        <input type="radio" wire:model.live="mode" value="network" class="mt-1 accent-brand-600" @disabled(! $networkEnabled)>
                        <span><span class="block text-sm font-semibold">Impressora de rede (IP)</span><span class="text-xs text-stone-500">{{ $networkEnabled ? 'Envia direto para a impressora térmica na porta 9100.' : 'Disponível só com o sistema instalado na rede do restaurante.' }}</span></span>
                    </label>
                </div>
                @error('mode') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            @if ($mode === 'network')
                <div class="grid grid-cols-[1fr_6rem] gap-2">
                    <div>
                        <label for="host" class="label">IP da impressora</label>
                        <input wire:model="host" id="host" type="text" class="input" placeholder="192.168.0.50">
                        @error('host') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="port" class="label">Porta</label>
                        <input wire:model="port" id="port" type="number" class="input">
                        @error('port') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label for="columns" class="label">Papel</label>
                    <select wire:model="columns" id="columns" class="input">
                        <option value="48">80 mm</option>
                        <option value="42">80 mm (fonte maior)</option>
                        <option value="32">58 mm</option>
                    </select>
                </div>
                <div>
                    <label for="copies" class="label">Vias</label>
                    <select wire:model="copies" id="copies" class="input">
                        @foreach ([1, 2, 3] as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                    </select>
                </div>
            </div>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="printsFullOrder" class="checkbox mt-0.5"> <span><span class="font-medium">Pedido completo, com valores</span><span class="block text-xs text-stone-500">Para expedição/caixa: conferir, embalar e cobrar (troco, endereço).</span></span></label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="isDefault" class="checkbox"> Setor padrão (itens de categorias sem setor)</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="isActive" class="checkbox"> Ativo</label>
            <div class="flex justify-end gap-2">
                @if ($editingId)<button type="button" wire:click="cancel" class="btn-secondary">Cancelar</button>@endif
                <button type="submit" class="btn-primary"><x-icon name="check" class="size-4" /> Salvar</button>
            </div>
        </form>
    </div>
</div>
