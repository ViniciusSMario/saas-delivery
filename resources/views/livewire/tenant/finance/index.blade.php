<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">Financeiro</h1>
        <div class="flex gap-2">
            @can('finance.manage')
                <a href="{{ route('finance.accounts') }}" class="btn-secondary" wire:navigate>Contas</a>
                <a href="{{ route('finance.create', ['tipo' => 'payable']) }}" class="btn-secondary" wire:navigate>+ A pagar</a>
                <a href="{{ route('finance.create', ['tipo' => 'receivable']) }}" class="btn-primary" wire:navigate>+ A receber</a>
            @endcan
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($accounts as $account)
            <div class="card card-body" wire:key="acc-{{ $account->id }}">
                <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">{{ $account->name }}</p>
                <p @class(['mt-1 text-xl font-semibold', 'text-red-700' => $account->balance()->cents < 0])>{{ $account->balance()->format() }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <button type="button" wire:click="$set('status', 'overdue')" class="card card-body text-left hover:border-red-400">
            <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">A pagar vencido</p>
            <p @class(['mt-1 text-xl font-semibold', 'text-red-700' => ! $overduePayable->isZero()])>{{ $overduePayable->format() }}</p>
        </button>
        <div class="card card-body">
            <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">A pagar em 7 dias</p>
            <p class="mt-1 text-xl font-semibold">{{ $next7Payable->format() }}</p>
        </div>
        <div class="card card-body">
            <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">A receber em aberto</p>
            <p class="mt-1 text-xl font-semibold">{{ $openReceivable->format() }}</p>
        </div>
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
        <table class="table">
            <thead class="bg-stone-50"><tr><th>Vencimento</th><th>Descrição</th><th>Categoria</th><th>Situação</th><th class="text-right">Valor</th><th></th></tr></thead>
            <tbody class="divide-y divide-stone-100">
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
                    <tr><td colspan="6" class="py-8 text-center text-stone-500">Nenhum lançamento.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $entries->links() }}

    @if ($paying)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-stone-900/40 p-4 sm:items-center" wire:click.self="$set('payingId', null)">
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
