<div class="space-y-6">
    <x-ui.page-header title="Financeiro" subtitle="Contas, saldos, contas a pagar e a receber.">
        @can('finance.manage')
            <a href="{{ route('finance.accounts') }}" class="btn-secondary" wire:navigate><x-icon name="building" class="size-4" /> Contas</a>
            <a href="{{ route('finance.create', ['tipo' => 'payable']) }}" class="btn-secondary" wire:navigate><x-icon name="minus" class="size-4" /> A pagar</a>
            <a href="{{ route('finance.create', ['tipo' => 'receivable']) }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> A receber</a>
        @endcan
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($accounts as $account)
            <x-ui.stat :label="$account->name" :value="$account->balance()->format()" icon="wallet" :tone="$account->balance()->cents < 0 ? 'red' : 'green'" wire:key="acc-{{ $account->id }}" />
        @endforeach
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <button type="button" wire:click="$set('status', 'overdue')" class="card card-body flex items-start gap-4 text-left transition hover:border-red-300 hover:shadow-md">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600"><x-icon name="alert" class="size-5" /></span>
            <span>
                <span class="stat-label block">A pagar vencido</span>
                <span @class(['stat-value block', 'text-red-600' => ! $overduePayable->isZero()])>{{ $overduePayable->format() }}</span>
            </span>
        </button>
        <x-ui.stat label="A pagar em 7 dias" :value="$next7Payable->format()" icon="calendar" tone="amber" />
        <x-ui.stat label="A receber em aberto" :value="$openReceivable->format()" icon="trending" tone="blue" />
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <select wire:model.live="status" class="input w-auto" aria-label="Situação">
            <option value="open">Em aberto (vencimento no mês)</option>
            <option value="overdue">Vencidos</option>
            <option value="paid">Pagos/recebidos no mês</option>
            <option value="all">Todos do mês</option>
        </select>
        <select wire:model.live="kind" class="input w-auto" aria-label="Tipo">
            <option value="">A pagar e a receber</option>
            <option value="payable">A pagar</option>
            <option value="receivable">A receber</option>
        </select>
        @if ($status !== 'overdue')
            <input wire:model.live="month" type="month" class="input w-auto" aria-label="Mês">
        @endif
        <span class="text-sm text-stone-500">Mês: entrou {{ $monthIn->format() }} · saiu {{ $monthOut->format() }} · resultado {{ $monthIn->subtract($monthOut)->format() }}</span>
    </div>

    <div class="card overflow-x-auto">
        <table class="table table-cards">
            <thead><tr><th data-card="field">Vencimento</th><th data-card="title">Descrição</th><th>Categoria</th><th>Situação</th><th class="text-right">Valor</th><th></th></tr></thead>
            <tbody>
                @forelse ($entries as $entry)
                    @php($label = $entry->statusLabel($today))
                    <tr wire:key="e-{{ $entry->id }}">
                        <td class="whitespace-nowrap">{{ $entry->due_date->format('d/m/Y') }}</td>
                        <td>
                            <p class="font-medium text-stone-900">{{ $entry->description }}</p>
                            <p class="text-xs text-stone-500">{{ $entry->kindLabel() }}{{ $entry->counterparty ? ' · '.$entry->counterparty : '' }}{{ $entry->recurrence === 'monthly' ? ' · mensal' : '' }}</p>
                        </td>
                        <td class="text-stone-500">{{ $entry->category?->name ?? '—' }}</td>
                        <td>
                            <span @class(['badge', 'bg-green-100 text-green-800' => $label === 'Pago', 'bg-red-100 text-red-700' => $label === 'Vencido', 'bg-stone-100 text-stone-600' => in_array($label, ['Em aberto', 'Cancelado'])])>{{ $label }}</span>
                            @if ($entry->paid_on)<span class="block text-xs text-stone-500">{{ $entry->paid_on->format('d/m') }} · {{ $entry->account?->name }}</span>@endif
                        </td>
                        <td @class(['text-right font-medium whitespace-nowrap', 'text-red-700' => $entry->kind === 'payable', 'text-green-700' => $entry->kind === 'receivable'])>
                            {{ $entry->kind === 'payable' ? '- ' : '' }}{{ $entry->amount_cents->format() }}
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @can('finance.manage')
                                @if ($entry->status === 'open')
                                    <button type="button" wire:click="startPay({{ $entry->id }})" class="btn-link">{{ $entry->kind === 'payable' ? 'Pagar' : 'Receber' }}</button>
                                    <a href="{{ route('finance.edit', $entry) }}" class="btn-link ml-2" wire:navigate>Editar</a>
                                    <button type="button" wire:click="cancel({{ $entry->id }})" wire:confirm="Cancelar este lançamento?" class="btn-link ml-2 text-red-700">Cancelar</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">
                        <x-ui.empty icon="trending" title="Nenhum lançamento" text="Contas a pagar e a receber deste período aparecem aqui. As vendas pagas entram sozinhas quando o caixa fecha.">
                            @can('finance.manage')
                                <a href="{{ route('finance.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> Novo lançamento</a>
                            @endcan
                        </x-ui.empty>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $entries->links() }}

    @if ($paying)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-stone-950/50 p-4 backdrop-blur-sm sm:items-center" wire:click.self="$set('payingId', null)">
            <form wire:submit="pay" class="card w-full max-w-sm space-y-4 p-5">
                <div>
                    <h3 class="text-lg font-semibold">{{ $paying->kind === 'payable' ? 'Registrar pagamento' : 'Registrar recebimento' }}</h3>
                    <p class="text-sm text-stone-500">{{ $paying->description }} · {{ $paying->amount_cents->format() }}</p>
                </div>
                <div>
                    <label for="accountId" class="label">Conta</label>
                    <select wire:model="accountId" id="accountId" class="input">
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                    @error('accountId') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="paidOn" class="label">Data</label>
                    <input wire:model="paidOn" id="paidOn" type="date" class="input">
                    @error('paidOn') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                @if ($paying->recurrence === 'monthly')<p class="hint">Recorrente: o lançamento do próximo mês é criado automaticamente.</p>@endif
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('payingId', null)" class="btn-secondary">Voltar</button>
                    <button type="submit" class="btn-primary">Confirmar</button>
                </div>
            </form>
        </div>
    @endif
</div>
