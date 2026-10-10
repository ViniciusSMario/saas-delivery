<div class="space-y-6" @if ($generating) wire:poll.4s @endif>
    <x-ui.page-header title="Exportar dados" subtitle="Baixe todos os dados do seu estabelecimento: clientes, pedidos, cardápio, caixa, financeiro, estoque e fotos." />

    @error('export') <div class="alert-error"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $message }}</div> @enderror

    <section class="card card-body flex flex-wrap items-center gap-4">
        <span class="flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600"><x-icon name="download" class="size-6" /></span>
        <div class="min-w-0 flex-1">
            <p class="font-semibold">Um arquivo .zip com planilhas (abrem no Excel) e as fotos</p>
            <p class="text-sm text-stone-500">Senhas e credenciais de integrações não entram. Avisamos por e-mail quando ficar pronto; o arquivo fica disponível por {{ \App\Modules\Exports\Models\DataExport::KEEP_DAYS }} dias.</p>
        </div>
        <button type="button" wire:click="request" class="btn-primary" @disabled($generating)>
            <x-icon name="download" class="size-4" /> {{ $generating ? 'Gerando...' : 'Gerar exportação' }}
        </button>
    </section>

    <section class="card overflow-hidden">
        <div class="card-header"><h2 class="section-title">Exportações</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Pedida em</th><th>Por</th><th>Situação</th><th class="text-right">Tamanho</th><th></th></tr></thead>
                <tbody>
                    @forelse ($exports as $export)
                        <tr wire:key="exp-{{ $export->id }}">
                            <td class="whitespace-nowrap">{{ $export->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</td>
                            <td>{{ $export->requester?->name ?? '—' }}</td>
                            <td>
                                @if ($export->status === 'pending') <span class="badge-amber">Gerando</span>
                                @elseif ($export->status === 'failed') <span class="badge-red" title="{{ $export->error }}">Falhou</span>
                                @elseif ($export->isAvailable()) <span class="badge-green">Pronta</span>
                                @else <span class="badge-gray">Expirada</span> @endif
                            </td>
                            <td class="text-right tabular-nums text-stone-600">{{ $export->size_bytes ? number_format($export->size_bytes / 1024 / 1024, 1, ',', '.').' MB' : '—' }}</td>
                            <td class="text-right">
                                @if ($export->isAvailable() && $export->path)
                                    <a href="{{ route('data-exports.download', $export) }}" class="btn-secondary btn-sm"><x-icon name="download" class="size-4" /> Baixar</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ui.empty icon="download" title="Nenhuma exportação ainda" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
