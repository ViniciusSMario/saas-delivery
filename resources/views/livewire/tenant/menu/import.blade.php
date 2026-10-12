@php
    $actionStyle = [
        'create' => ['Novo', 'badge-green'],
        'update' => ['Atualiza', 'badge-blue'],
        'same' => ['Sem mudança', 'badge-gray'],
        'skip' => ['Pulado', 'badge-gray'],
    ];
    $money = fn (?int $cents) => $cents === null ? '—' : 'R$ '.number_format($cents / 100, 2, ',', '.');
    $plural = fn (int $n, string $one, string $many) => $n === 1 ? "1 {$one}" : "{$n} {$many}";
@endphp
<div class="space-y-6">
    <x-ui.page-header title="Importar cardápio" subtitle="Cadastre ou atualize muitos produtos de uma vez com uma planilha do Excel ou do Google Planilhas."
                      :back="route('menu.products.index')" back-label="Produtos" />

    @if ($result)
        <div class="alert-success">
            <x-icon name="check" class="mt-0.5 size-4" />
            <div>
                <p class="font-semibold">Importação concluída.</p>
                <p class="font-normal">
                    {{ collect([
                        $result['products_created'] ? $plural($result['products_created'], 'produto criado', 'produtos criados') : null,
                        $result['products_updated'] ? $plural($result['products_updated'], 'produto atualizado', 'produtos atualizados') : null,
                        $result['categories_created'] ? $plural($result['categories_created'], 'categoria nova', 'categorias novas') : null,
                        $result['groups_created'] ? $plural($result['groups_created'], 'grupo de adicionais novo', 'grupos de adicionais novos') : null,
                        ($result['photos'] ?? 0) ? $plural($result['photos'], 'foto', 'fotos') : null,
                    ])->filter()->implode(' · ') ?: 'Nada mudou.' }}
                </p>
                <p class="mt-2 flex flex-wrap gap-3">
                    <a href="{{ route('menu.products.index') }}" class="font-semibold underline" wire:navigate>Ver produtos</a>
                    <a href="{{ url('/cardapio') }}" target="_blank" class="font-semibold underline">Ver o cardápio</a>
                </p>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[22rem_1fr]">
        {{-- 1. Modelo  2. Envio --}}
        <div class="space-y-6">
            <section class="card card-body space-y-3">
                <h2 class="section-title flex items-center gap-2"><span class="flex size-6 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">1</span> Baixe o modelo</h2>
                <p class="text-sm text-stone-600">Uma linha por produto. Os adicionais (bordas, ponto da carne...) ficam na aba Adicionais.</p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('menu.import.template', 'xlsx') }}" class="btn-secondary btn-sm"><x-icon name="download" class="size-4" /> Modelo Excel</a>
                    <a href="{{ route('menu.import.template', 'csv') }}" class="btn-ghost btn-sm"><x-icon name="download" class="size-4" /> CSV (só produtos)</a>
                </div>
                <div class="border-t border-stone-100 pt-3">
                    <p class="text-sm text-stone-600">Já tem produtos? Baixe o cardápio atual, mude preços e descrições na planilha e importe de volta.</p>
                    <a href="{{ route('menu.import.export', 'xlsx') }}" class="btn-link mt-1 inline-flex items-center gap-1 text-sm"><x-icon name="download" class="size-4" /> Baixar meu cardápio</a>
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
                    <div wire:loading wire:target="file" class="hint">Enviando e conferindo...</div>
                    @error('file') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <p class="label">Fotos em .zip <span class="font-normal text-stone-400">(opcional)</span></p>
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed border-stone-300 p-3 transition hover:border-brand-400 has-focus-visible:border-brand-500">
                        <input wire:model="photos" id="photos" type="file" accept=".zip" class="sr-only">
                        <span class="btn-secondary btn-sm pointer-events-none shrink-0"><x-icon name="download" class="size-4 rotate-180" /> Escolher .zip</span>
                        <span class="min-w-0 truncate text-sm {{ $photos ? 'font-medium text-stone-800' : 'text-stone-500' }}">{{ $photos?->getClientOriginalName() ?? 'Nenhum arquivo' }}</span>
                    </label>
                    <p class="hint">Cada foto com o nome indicado na coluna Foto (ou o código, ou o nome do produto). JPG, PNG ou WebP, até 4 MB cada.</p>
                    <div wire:loading wire:target="photos" class="hint">Enviando as fotos...</div>
                    @error('photos') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <fieldset>
                    <legend class="label">Produtos que já existem</legend>
                    <div class="space-y-2">
                        @foreach (['update' => ['Atualizar', 'Preço, descrição, categoria e o que mais vier preenchido na planilha.'], 'skip' => ['Não mexer', 'Só cria os produtos novos.']] as $value => [$label, $text])
                            <label @class(['flex cursor-pointer gap-3 rounded-xl border-2 p-3 text-sm', 'border-brand-600 bg-brand-50' => $mode === $value, 'border-stone-200' => $mode !== $value])>
                                <input type="radio" wire:model.live="mode" value="{{ $value }}" class="mt-0.5">
                                <span><strong class="block">{{ $label }}</strong><span class="text-stone-600">{{ $text }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </section>
        </div>

        {{-- 3. Prévia --}}
        <section class="card min-w-0">
            <div class="card-header">
                <h2 class="section-title flex items-center gap-2"><span class="flex size-6 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">3</span> Confira e importe</h2>
                @if ($preview)
                    <button type="button" wire:click="startOver" class="btn-ghost btn-sm">Enviar outra</button>
                @endif
            </div>

            <div wire:loading.flex wire:target="file,photos,mode,apply" class="items-center gap-2 px-5 py-10 text-sm text-stone-500">
                <x-icon name="refresh" class="size-4 animate-spin" /> <span wire:loading wire:target="apply">Importando...</span><span wire:loading wire:target="file,photos,mode">Conferindo a planilha...</span>
            </div>

            <div wire:loading.remove wire:target="file,photos,mode,apply">
                @if (! $preview)
                    <x-ui.empty icon="file" title="Nenhuma planilha enviada" text="Envie a planilha para ver o que vai ser criado e atualizado antes de gravar qualquer coisa." />
                @else
                    @php($c = $preview['counts'])
                    <div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-4">
                        @foreach ([
                            ['Produtos novos', $c['products_created'], 'text-emerald-700'],
                            ['Atualizados', $c['products_updated'], 'text-sky-700'],
                            ['Sem mudança', $c['products_same'] + $c['products_skipped'], 'text-stone-500'],
                            ['Fotos', $c['photos'], 'text-stone-700'],
                        ] as [$label, $value, $tone])
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
                                        @foreach (array_slice($preview['errors'], 0, 50) as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                    @if (count($preview['errors']) > 50)<p class="mt-1 font-normal">E mais {{ count($preview['errors']) - 50 }}.</p>@endif
                                </div>
                            </div>
                        @endif

                        @foreach ($preview['warnings'] as $warning)
                            <div class="alert-warning"><x-icon name="info" class="mt-0.5 size-4" /> <span>{{ $warning }}</span></div>
                        @endforeach

                        @if ($preview['categories'] || $preview['groups'])
                            <div class="rounded-xl border border-stone-200 p-3 text-sm">
                                @if ($preview['categories'])
                                    <p><strong>Categorias novas:</strong> {{ implode(', ', $preview['categories']) }}</p>
                                @endif
                                @foreach ($preview['groups'] as $group)
                                    <p class="mt-1"><span class="{{ $group['action'] === 'create' ? 'badge-green' : 'badge-blue' }}">{{ $group['action'] === 'create' ? 'Novo' : 'Atualiza' }}</span>
                                        <strong>{{ $group['name'] }}</strong> · {{ $group['rule'] }} · {{ $plural($group['options'], 'opção', 'opções') }}{{ $group['action'] === 'update' && $group['new_options'] ? ' ('.$group['new_options'].' nova(s))' : '' }}</p>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if ($preview['products'])
                    <div class="mt-4 overflow-x-auto">
                        <table class="table">
                            <thead><tr><th>Linha</th><th></th><th>Produto</th><th class="text-right">Preço</th><th>O que muda</th></tr></thead>
                            <tbody>
                                @foreach (array_slice($preview['products'], 0, $previewRows) as $row)
                                    @php([$label, $badge] = $actionStyle[$row['action']])
                                    <tr wire:key="row-{{ $row['line'] }}">
                                        <td class="text-stone-500 tabular-nums">{{ $row['line'] }}</td>
                                        <td><span class="{{ $badge }}">{{ $label }}</span></td>
                                        <td class="min-w-48">
                                            <span class="font-medium">{{ $row['name'] }}</span>
                                            @if ($row['photo'])<span title="Com foto do .zip"><x-icon name="eye" class="inline size-3.5 text-stone-400" /></span>@endif
                                            <span class="block text-xs text-stone-500">{{ $row['category'] }}{{ $row['code'] ? ' · cód. '.$row['code'] : '' }}</span>
                                        </td>
                                        <td class="text-right whitespace-nowrap tabular-nums">{{ $money($row['price']) }}</td>
                                        <td class="text-xs text-stone-600">{{ $row['action'] === 'create' ? '' : implode(' · ', $row['changes']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if (count($preview['products']) > $previewRows)
                            <p class="px-5 py-3 text-sm text-stone-500">Mostrando {{ $previewRows }} de {{ count($preview['products']) }} produtos.</p>
                        @endif
                    </div>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-3 border-t border-stone-100 px-5 py-4">
                        @if (! $preview['errors'] && ! $preview['hasWork'])
                            <p class="mr-auto text-sm text-stone-500">A planilha está igual ao cardápio: nada para importar.</p>
                        @endif
                        <button type="button" wire:click="apply" class="btn-primary" @disabled($preview['errors'] || ! $preview['hasWork']) wire:loading.attr="disabled">
                            <x-icon name="check" class="size-4" /> Importar agora
                        </button>
                    </div>
                @endif
            </div>
        </section>
    </div>

    {{-- Histórico --}}
    @if ($imports->isNotEmpty())
        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="font-semibold">Importações</h2></div>
            @error('import') <div class="alert-error m-4"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $message }}</div> @enderror
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Quando</th><th>Arquivo</th><th>Resultado</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($imports as $import)
                            @php($s = $import->summary)
                            <tr wire:key="import-{{ $import->id }}" @class(['opacity-60' => $import->status === 'undone'])>
                                <td class="text-sm whitespace-nowrap">{{ $import->created_at->timezone($timezone)->format('d/m/Y H:i') }}<span class="block text-xs text-stone-500">{{ $import->user?->name }}</span></td>
                                <td class="text-sm">{{ $import->file_name }}@if ($import->photos_file_name)<span class="block text-xs text-stone-500">+ {{ $import->photos_file_name }}</span>@endif</td>
                                <td class="text-sm text-stone-600">{{ $s['products_created'] ?? 0 }} novos · {{ $s['products_updated'] ?? 0 }} atualizados{{ ($s['photos'] ?? 0) ? ' · '.$s['photos'].' fotos' : '' }}</td>
                                <td class="text-right">
                                    @if ($import->status === 'undone')
                                        <span class="badge-gray">Desfeita {{ $import->undone_at?->timezone($timezone)->format('d/m H:i') }}</span>
                                    @elseif ($undoable === $import->id)
                                        <button type="button" wire:click="undo({{ $import->id }})" class="btn-ghost btn-sm text-red-600"
                                                wire:confirm="Desfazer esta importação? Os produtos, categorias e adicionais criados por ela saem do cardápio e os alterados voltam ao que eram. Mudanças feitas depois nesses produtos se perdem.">
                                            Desfazer
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
