@php $max = 1; @endphp
<div class="space-y-6">
    <x-ui.page-header title="Relatórios" subtitle="Vendas, produtos, caixas e estoque no período escolhido." />

    <div class="card flex flex-wrap items-center gap-2 p-3">
        <div class="flex flex-wrap gap-1 rounded-xl bg-stone-100 p-1 text-sm">
            @foreach (['today' => 'Hoje', 'week' => '7 dias', 'month' => 'Este mês', 'last_month' => 'Mês passado'] as $key => $label)
                <button type="button" wire:click="preset('{{ $key }}')" class="rounded-lg px-3 py-1.5 font-semibold text-stone-600 transition hover:bg-white hover:text-stone-900 hover:shadow-sm">{{ $label }}</button>
            @endforeach
        </div>
        <div class="ml-auto flex items-center gap-2 text-sm text-stone-500">
            <x-icon name="calendar" class="size-4" />
            <input wire:model.live="from" type="date" class="input w-auto" aria-label="De">
            <span>até</span>
            <input wire:model.live="to" type="date" class="input w-auto" aria-label="Até">
        </div>
    </div>

    {{-- Resumo do período --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Vendas" :value="$summary['revenue']->format()" icon="trending" tone="brand" />
        <x-ui.stat label="Pedidos / contas" :value="$summary['count']" icon="orders" tone="blue" />
        <x-ui.stat label="Ticket médio" :value="$summary['average']->format()" icon="cash" tone="green" />
        <x-ui.stat label="Taxa de serviço · descontos" :value="$summary['service_fee']->format().' · '.$summary['discounts']->format()" icon="tag" tone="violet" />
    </div>

    <nav class="flex flex-wrap gap-1 border-b border-stone-200">
        @foreach ($tabs as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" @class(['tab', 'tab-active' => $tab === $key])>{{ $label }}</button>
        @endforeach
    </nav>

    @if ($tab === 'sales')
        <div class="grid gap-6 lg:grid-cols-3">
            <section class="card overflow-x-auto lg:col-span-2">
                <div class="flex items-center justify-between px-5 pt-5">
                    <h2 class="section-title">Por dia</h2>
                    <a href="{{ route('reports.export', ['type' => 'vendas'] + $query) }}" class="btn-link text-sm">Exportar CSV</a>
                </div>
                @php($max = max(1, $daily->max(fn ($d) => $d['total']->cents)))
                <table class="table">
                    <thead><tr><th>Dia</th><th class="w-1/3"></th><th class="text-right">Pedidos</th><th class="text-right">Ticket</th><th class="text-right">Vendas</th></tr></thead>
                    <tbody>
                        @foreach ($daily as $day)
                            <tr>
                                <td class="whitespace-nowrap">{{ $day['date']->locale('pt_BR')->isoFormat('ddd, DD/MM') }}</td>
                                <td><div class="h-2 rounded bg-brand-500" style="width: {{ round($day['total']->cents / $max * 100) }}%"></div></td>
                                <td class="text-right">{{ $day['count'] }}</td>
                                <td class="text-right whitespace-nowrap">{{ $day['average']->format() }}</td>
                                <td class="text-right font-medium whitespace-nowrap">{{ $day['total']->format() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
            <div class="space-y-6">
                <section class="card card-body">
                    <h2 class="section-title">Por canal</h2>
                    <dl class="mt-2 space-y-1 text-sm">
                        @foreach ($summary['channels'] as $channel)
                            <div class="flex justify-between"><dt>{{ $channel['label'] }} ({{ $channel['count'] }})</dt><dd class="font-medium">{{ $channel['total']->format() }}</dd></div>
                        @endforeach
                    </dl>
                </section>
                <section class="card card-body">
                    <h2 class="section-title">Recebido por forma de pagamento</h2>
                    <dl class="mt-2 space-y-1 text-sm">
                        @forelse ($methods as $row)
                            <div class="flex justify-between"><dt>{{ $row['method'] }}</dt><dd class="font-medium">{{ $row['total']->format() }}</dd></div>
                        @empty
                            <p class="text-stone-500">Nada recebido no caixa no período.</p>
                        @endforelse
                    </dl>
                    <p class="hint mt-2">Pelo livro do caixa: entrega/retirada entra quando é concluída.</p>
                </section>
            </div>
        </div>
    @elseif ($tab === 'products')
        <section class="card overflow-x-auto">
            <div class="flex items-center justify-between px-5 pt-5">
                <h2 class="section-title">Mais vendidos</h2>
                <a href="{{ route('reports.export', ['type' => 'produtos'] + $query) }}" class="btn-link text-sm">Exportar CSV</a>
            </div>
            <table class="table">
                <thead><tr><th>Produto</th><th class="text-right">Qtd.</th><th class="text-right">Faturamento</th><th class="text-right">Custo (ficha)</th><th class="text-right">Margem</th></tr></thead>
                <tbody>
                    @forelse ($products as $row)
                        <tr>
                            <td class="font-medium">{{ $row['name'] }}</td>
                            <td class="text-right">{{ $row['quantity'] }}</td>
                            <td class="text-right whitespace-nowrap">{{ $row['revenue']->format() }}</td>
                            <td class="text-right whitespace-nowrap">{{ $row['cost']?->format() ?? '—' }}</td>
                            <td class="text-right">{{ $row['margin'] !== null ? number_format($row['margin'], 1, ',', '.').'%' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ui.empty icon="trending" title="Nenhuma venda no período" /></td></tr>
                    @endforelse
                </tbody>
            </table>
            <p class="hint px-5 pb-4">Inclui itens lançados nas mesas. Custo pelo custo médio atual dos insumos.</p>
        </section>
    @elseif ($tab === 'hours')
        @php($max = max(1, max($hours)))
        <section class="card card-body">
            <h2 class="section-title">Pedidos por hora</h2>
            <div class="mt-4 flex h-48 items-end gap-1">
                @foreach ($hours as $hour => $count)
                    <div class="flex flex-1 flex-col items-center gap-1" title="{{ $hour }}h: {{ $count }} pedido(s)">
                        <span class="text-[10px] text-stone-500">{{ $count ?: '' }}</span>
                        <div class="w-full rounded-t bg-brand-500" style="height: {{ round($count / $max * 140) }}px"></div>
                        <span class="text-[10px] text-stone-500">{{ $hour }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @elseif ($tab === 'losses')
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="card overflow-x-auto">
                <div class="card-header"><h2 class="section-title">Cancelamentos ({{ $cancellations->count() }})</h2></div>
                <table class="table">
                    <tbody>
                        @forelse ($cancellations as $order)
                            <tr>
                                <td class="whitespace-nowrap text-stone-500">{{ $order->cancelled_at->timezone($timezone)->format('d/m H:i') }}</td>
                                <td>#{{ $order->number }} · {{ $order->cancel_reason }}<span class="block text-xs text-stone-500">{{ $order->history->first()?->user?->name }}</span></td>
                                <td class="text-right whitespace-nowrap">{{ $order->total_cents->format() }}</td>
                            </tr>
                        @empty
                            <tr><td class="py-6 text-center text-stone-500">Nenhum cancelamento.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
            <section class="card overflow-x-auto">
                <div class="card-header"><h2 class="section-title">Descontos e taxa de serviço retirada</h2></div>
                <table class="table">
                    <tbody>
                        @forelse ($discounts as $row)
                            <tr>
                                <td class="whitespace-nowrap text-stone-500">{{ $row['when']?->timezone($timezone)->format('d/m H:i') }}</td>
                                <td>{{ $row['what'] }}{{ $row['note'] ? ' · '.$row['note'] : '' }}<span class="block text-xs text-stone-500">{{ $row['by'] }}</span></td>
                                <td class="text-right whitespace-nowrap">{{ $row['amount']->isZero() ? '—' : $row['amount']->format() }}</td>
                            </tr>
                        @empty
                            <tr><td class="py-6 text-center text-stone-500">Nenhum desconto.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    @elseif ($tab === 'cash')
        <section class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Caixa</th><th>Operador</th><th>Período</th><th class="text-right">Diferença</th><th></th></tr></thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr>
                            <td>{{ $session->register->name }}</td>
                            <td>{{ $session->operator->name }}</td>
                            <td class="text-stone-500">{{ $session->opened_at->timezone($timezone)->format('d/m H:i') }} – {{ $session->closed_at->timezone($timezone)->format('H:i') }}</td>
                            <td @class(['text-right font-medium', 'text-red-700' => $session->difference_cents?->cents < 0, 'text-green-700' => $session->difference_cents?->cents > 0])>{{ $session->difference_cents?->format() }}</td>
                            <td class="text-right"><a href="{{ route('cash.report', $session) }}" class="btn-link" wire:navigate>Relatório</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ui.empty icon="wallet" title="Nenhum caixa fechado no período" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endif
</div>
