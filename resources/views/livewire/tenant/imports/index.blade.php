@php
    $actionStyle = [
        'create' => ['Novo', 'badge-green'],
        'update' => ['Atualiza', 'badge-blue'],
        'same' => ['Sem mudança', 'badge-gray'],
        'skip' => ['Pulado', 'badge-gray'],
    ];
    $tabIcons = ['clientes' => 'users', 'bairros' => 'truck', 'insumos' => 'box', 'fichas' => 'file'];
    [$one, $many, $gender] = $typeInfo?->noun() ?? ['item', 'itens', 'm'];
    $o = $gender === 'f' ? 'a' : 'o'; // "novo"/"nova", "atualizado"/"atualizada"
    $plural = fn (int $n, string $one, string $many) => $n === 1 ? "1 {$one}" : "{$n} {$many}";
@endphp
<div class="space-y-6">
    <x-ui.page-header title="Importar dados" subtitle="Traga de uma planilha o que hoje está em outro sistema ou no caderno, em vez de cadastrar um por um." />

    {{-- Tipos --}}
    <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 scrollbar-none sm:mx-0 sm:px-0" aria-label="O que importar">
        @if ($canMenu)
            <a href="{{ route('menu.import') }}" wire:navigate class="chip h-10"><x-icon name="book" class="size-4" /> Cardápio</a>
        @endif
        @foreach ($types as $slug => $info)
            <button type="button" wire:click="choose('{{ $slug }}')" wire:key="tab-{{ $slug }}" @class(['chip h-10', 'chip-active' => $type === $slug])>
                <x-icon :name="$tabIcons[$slug] ?? 'file'" class="size-4" /> {{ $info->title() }}
            </button>
        @endforeach
    </nav>

    @if ($typeInfo)
        @if ($result)
            <div class="alert-success">
                <x-icon name="check" class="mt-0.5 size-4" />
                <span><strong>Importação concluída.</strong>
                    {{ collect([
                        $result['created'] ? $plural($result['created'], "{$one} nov{$o}", "{$many} nov{$o}s") : null,
                        $result['updated'] ? $plural($result['updated'], "{$one} atualizad{$o}", "{$many} atualizad{$o}s") : null,
                    ])->filter()->implode(' · ') ?: 'Nada mudou.' }}
                </span>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[22rem_1fr]">
            <div class="space-y-6">
                <section class="card card-body space-y-3">
                    <h2 class="section-title flex items-center gap-2"><span class="flex size-6 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">1</span> Baixe o modelo</h2>
                    <p class="text-sm text-stone-600">Colunas: {{ implode(', ', array_column($typeInfo->columns(), 0)) }}. Obrigatórias: {{ implode(', ', array_map(fn ($k) => $typeInfo->columns()[$k][0], $typeInfo->required())) }}.</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('imports.template', [$type, 'xlsx']) }}" class="btn-secondary btn-sm"><x-icon name="download" class="size-4" /> Modelo Excel</a>
                        <a href="{{ route('imports.template', [$type, 'csv']) }}" class="btn-ghost btn-sm"><x-icon name="download" class="size-4" /> CSV</a>
                    </div>
                    <div class="border-t border-stone-100 pt-3">
                        <p class="text-sm text-stone-600">Para editar em massa, baixe o que já está cadastrado, mude na planilha e importe de volta.</p>
                        <a href="{{ route('imports.export', [$type, 'xlsx']) }}" class="btn-link mt-1 inline-flex items-center gap-1 text-sm"><x-icon name="download" class="size-4" /> Baixar {{ $many }} atuais</a>
                    </div>
                </section>

                <section class="card card-body space-y-4">
                    <h2 class="section-title flex items-center gap-2"><span class="flex size-6 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">2</span> Envie a planilha</h2>
                    <div>
                        <p class="label">Planilha (.xlsx ou .csv)</p>
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed border-stone-300 p-3 transition hover:border-brand-400 has-focus-visible:border-brand-500">
                            <input wire:model="file" id="file" type="file" accept=".xlsx,.csv" class="sr-only">
                            <span class="btn-secondary btn-sm pointer-events-none shrink-0"><x-icon name="file" class="size-4" /> Escolher planilha</span>
                            <span class="min-w-0 truncate text-sm {{ $file ? 'font-medium text-stone-800' : 'text-stone-500' }}">{{ $file?->getClientOriginalName() ?? 'Nenhum arquivo' }}</span>
                        </label>
                        @error('file') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <fieldset>
                        <legend class="label">{{ ucfirst($many) }} que já existem</legend>
                        <div class="space-y-2">
                            @foreach (['update' => ['Atualizar', 'Com o que vier preenchido na planilha (célula em branco não muda nada).'], 'skip' => ['Não mexer', 'Só cria os novos.']] as $value => [$label, $text])
                                <label @class(['flex cursor-pointer gap-3 rounded-xl border-2 p-3 text-sm', 'border-brand-600 bg-brand-50' => $mode === $value, 'border-stone-200' => $mode !== $value])>
                                    <input type="radio" wire:model.live="mode" value="{{ $value }}" class="mt-0.5">
                                    <span><strong class="block">{{ $label }}</strong><span class="text-stone-600">{{ $text }}</span></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </section>
            </div>

            <section class="card min-w-0">
                <div class="card-header">
                    <h2 class="section-title flex items-center gap-2"><span class="flex size-6 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">3</span> Confira e importe</h2>
                    @if ($preview)<button type="button" wire:click="startOver" class="btn-ghost btn-sm">Enviar outra</button>@endif
                </div>

                <div wire:loading.flex wire:target="file,mode,apply" class="items-center gap-2 px-5 py-10 text-sm text-stone-500">
                    <x-icon name="refresh" class="size-4 animate-spin" /> <span wire:loading wire:target="apply">Importando...</span><span wire:loading wire:target="file,mode">Conferindo a planilha...</span>
                </div>

                <div wire:loading.remove wire:target="file,mode,apply">
                    @if (! $preview)
                        <x-ui.empty icon="file" title="Nenhuma planilha enviada" text="Envie a planilha para ver o que vai ser criado e atualizado antes de gravar qualquer coisa." />
                    @else
                        @php($c = $preview['counts'])
                        <div class="grid grid-cols-3 gap-3 p-5">
                            @foreach ([["Nov{$o}s", $c['created'], 'text-emerald-700'], ["Atualizad{$o}s", $c['updated'], 'text-sky-700'], ['Sem mudança', $c['same'] + $c['skipped'], 'text-stone-500']] as [$label, $value, $tone])
                                <div class="rounded-xl bg-stone-50 p-3">
                                    <p class="text-xs font-semibold text-stone-500 uppercase">{{ $label }}</p>
                                    <p class="text-2xl font-extrabold tabular-nums {{ $tone }}">{{ $value }}</p>
                                </div>
                            @endforeach
                        </div>

                        <div class="space-y-3 px-5">
                            @if ($preview['errors'])
                                <div class="alert-error">
                                    <x-icon name="alert" class="mt-0.5 size-4" />
                                    <div class="min-w-0">
                                        <p class="font-semibold">Corrija {{ $plural(count($preview['errors']), 'problema', 'problemas') }} na planilha e envie de novo (nada foi gravado):</p>
                                        <ul class="mt-1 list-disc space-y-0.5 pl-5 font-normal">
                                            @foreach (array_slice($preview['errors'], 0, 50) as $error)<li>{{ $error }}</li>@endforeach
                                        </ul>
                                        @if (count($preview['errors']) > 50)<p class="mt-1 font-normal">E mais {{ count($preview['errors']) - 50 }}.</p>@endif
                                    </div>
                                </div>
                            @endif
                            @foreach ($preview['warnings'] as $warning)
                                <div class="alert-warning"><x-icon name="info" class="mt-0.5 size-4" /> <span>{{ $warning }}</span></div>
                            @endforeach
                        </div>

                        @if ($preview['rows'])
                            <div class="mt-4 overflow-x-auto">
                                <table class="table">
                                    <thead><tr><th>Linha</th><th></th><th>{{ ucfirst($one) }}</th><th>O que muda</th></tr></thead>
                                    <tbody>
                                        @foreach (array_slice($preview['rows'], 0, $previewRows) as $i => $row)
                                            @php([$label, $badge] = $actionStyle[$row['action']])
                                            <tr wire:key="row-{{ $i }}">
                                                <td class="text-stone-500 tabular-nums">{{ $row['line'] }}</td>
                                                <td><span class="{{ $badge }}">{{ $label }}</span></td>
                                                <td class="min-w-48"><span class="font-medium">{{ $row['label'] }}</span><span class="block text-xs text-stone-500">{{ $row['detail'] }}</span></td>
                                                <td class="text-xs text-stone-600">{{ implode(' · ', $row['changes']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @if (count($preview['rows']) > $previewRows)
                                    <p class="px-5 py-3 text-sm text-stone-500">Mostrando {{ $previewRows }} de {{ count($preview['rows']) }}.</p>
                                @endif
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-stone-100 px-5 py-4">
                            @if (! $preview['errors'] && ! $preview['hasWork'])
                                <p class="mr-auto text-sm text-stone-500">A planilha está igual ao que já está cadastrado: nada para importar.</p>
                            @endif
                            <button type="button" wire:click="apply" class="btn-primary" @disabled($preview['errors'] || ! $preview['hasWork']) wire:loading.attr="disabled">
                                <x-icon name="check" class="size-4" /> Importar agora
                            </button>
                        </div>
                    @endif
                </div>
            </section>
        </div>

        @if ($imports->isNotEmpty())
            <section class="card overflow-hidden">
                <div class="card-header"><h2 class="font-semibold">Importações de {{ $many }}</h2></div>
                @error('import') <div class="alert-error m-4"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $message }}</div> @enderror
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Quando</th><th>Arquivo</th><th>Resultado</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($imports as $import)
                                @php($s = $import->summary)
                                <tr wire:key="import-{{ $import->id }}" @class(['opacity-60' => $import->status === 'undone'])>
                                    <td class="text-sm whitespace-nowrap">{{ $import->created_at->timezone($timezone)->format('d/m/Y H:i') }}<span class="block text-xs text-stone-500">{{ $import->user?->name }}</span></td>
                                    <td class="text-sm">{{ $import->file_name }}</td>
                                    <td class="text-sm text-stone-600">{{ $s['created'] ?? 0 }} nov{{ $o }}s · {{ $s['updated'] ?? 0 }} atualizad{{ $o }}s</td>
                                    <td class="text-right">
                                        @if ($import->status === 'undone')
                                            <span class="badge-gray">Desfeita {{ $import->undone_at?->timezone($timezone)->format('d/m H:i') }}</span>
                                        @elseif ($undoable === $import->id)
                                            <button type="button" wire:click="undo({{ $import->id }})" class="btn-ghost btn-sm text-red-600"
                                                    wire:confirm="Desfazer esta importação? O que ela criou é removido e o que alterou volta ao que era. Mudanças feitas depois nesses registros se perdem.">Desfazer</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
</div>
