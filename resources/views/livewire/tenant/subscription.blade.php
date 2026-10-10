@php
    $statusBadge = match ($data['status']) {
        'trialing' => 'badge-blue',
        'active' => 'badge-green',
        'past_due' => 'badge-red',
        'canceled' => 'badge-gray',
        default => 'badge-violet',
    };
    $current = collect($data['plans'])->firstWhere('current', true);
@endphp
<div class="space-y-6">
    <x-ui.page-header title="Assinatura" subtitle="Seu plano, o uso dos limites e as faturas." />

    @error('plan') <div class="alert-error"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $message }}</div> @enderror

    {{-- Situação --}}
    <section class="card overflow-hidden">
        <div class="flex flex-wrap items-center gap-4 p-5 sm:p-6">
            <span class="flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600"><x-icon name="card" class="size-6" /></span>
            <div class="min-w-0 flex-1">
                <p class="flex flex-wrap items-center gap-2">
                    <span class="text-lg font-bold">{{ $current['name'] ?? 'Sem plano' }}</span>
                    <span class="{{ $statusBadge }}">{{ $data['status_label'] }}</span>
                </p>
                <p class="text-sm text-stone-500">
                    @switch($data['status'])
                        @case('trialing') Teste grátis: faltam <strong>{{ $data['trial_days_left'] }} dia(s)</strong> (até {{ $data['trial_ends_at'] }}). @break
                        @case('active') {{ $current['price'] ?? '' }}/mês · próximo vencimento {{ $data['period_ends_at'] }}. @break
                        @case('past_due') Há uma fatura em aberto. Pague para evitar a suspensão do acesso. @break
                        @case('canceled') Cancelada. O acesso vai até {{ $data['period_ends_at'] ?? $data['trial_ends_at'] ?? 'o fim do período' }}. @break
                        @default Acesso de cortesia: sem cobrança.
                    @endswitch
                </p>
            </div>
            @if ($data['pay_url'])
                <a href="{{ $data['pay_url'] }}" target="_blank" class="btn-primary"><x-icon name="wallet" class="size-4" /> Pagar fatura</a>
            @endif
        </div>

        <div class="grid gap-px border-t border-stone-100 bg-stone-100 sm:grid-cols-4">
            @foreach ($data['usage'] as $item)
                @php($pct = $item['limit'] ? min(100, round($item['used'] / max(1, $item['limit']) * 100)) : 0)
                <div class="bg-white p-4">
                    <p class="stat-label">{{ $item['label'] }}</p>
                    <p class="mt-1 text-lg font-bold tabular-nums">{{ $item['used'] }}<span class="text-sm font-medium text-stone-400"> / {{ $item['limit'] ?? '∞' }}</span></p>
                    @if ($item['limit'])
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-stone-100">
                            <div @class(['h-full rounded-full', 'bg-red-500' => $pct >= 100, 'bg-amber-500' => $pct >= 80 && $pct < 100, 'bg-emerald-500' => $pct < 80]) style="width: {{ $pct }}%"></div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    {{-- Planos --}}
    <section>
        <h2 class="section-title">{{ in_array($data['status'], ['active', 'past_due'], true) ? 'Trocar de plano' : 'Escolha seu plano' }}</h2>
        <p class="text-sm text-stone-500">Pagamento mensal por Pix, boleto ou cartão.{{ $data['status'] === 'trialing' ? ' A primeira cobrança vence no fim do teste.' : '' }}</p>
        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            @foreach ($data['plans'] as $plan)
                <div @class(['flex flex-col rounded-2xl border bg-white p-5', 'border-brand-500 ring-1 ring-brand-500' => $plan['current'], 'border-stone-200' => ! $plan['current']])>
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-bold">{{ $plan['name'] }}</h3>
                        @if ($plan['current'])<span class="badge-brand">Atual</span>@endif
                    </div>
                    <p class="mt-1 text-sm text-stone-500">{{ $plan['description'] }}</p>
                    <p class="mt-3"><span class="text-2xl font-extrabold tracking-tight">{{ $plan['price'] }}</span><span class="text-sm text-stone-500">/mês</span></p>
                    <ul class="mt-4 flex-1 space-y-1.5 text-sm">
                        @foreach ($plan['highlights'] as $line)
                            <li class="flex items-start gap-2"><x-icon name="check" class="mt-0.5 size-4 text-emerald-600" stroke="2.6" /> {{ $line }}</li>
                        @endforeach
                    </ul>
                    @php($subscribed = in_array($data['status'], ['active', 'past_due'], true) || ($data['status'] === 'trialing' && $data['pay_url']))
                    @if (! $plan['current'] || ! $subscribed)
                        <button type="button" wire:click="subscribe('{{ $plan['slug'] }}')" wire:loading.attr="disabled"
                                wire:confirm="{{ $plan['current'] ? 'Assinar o plano '.$plan['name'].'?' : 'Mudar para o plano '.$plan['name'].' ('.$plan['price'].'/mês)?' }}"
                                @class(['mt-5 w-full', 'btn-primary' => $plan['current'], 'btn-secondary' => ! $plan['current']])>
                            {{ $plan['current'] ? 'Assinar este plano' : ($subscribed ? 'Mudar para este' : 'Escolher este') }}
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    {{-- Faturas --}}
    <section class="card overflow-hidden">
        <div class="card-header"><h2 class="section-title">Faturas</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Vencimento</th><th>Descrição</th><th>Situação</th><th class="text-right">Valor</th><th></th></tr></thead>
                <tbody>
                    @forelse ($data['invoices'] as $invoice)
                        <tr>
                            <td class="whitespace-nowrap">{{ $invoice['due'] }}</td>
                            <td class="text-stone-600">{{ $invoice['description'] }}</td>
                            <td><span class="{{ $invoice['badge'] }}">{{ $invoice['status'] }}</span></td>
                            <td class="text-right font-semibold whitespace-nowrap tabular-nums">{{ $invoice['amount'] }}</td>
                            <td class="text-right">
                                @if ($invoice['url'])<a href="{{ $invoice['url'] }}" target="_blank" class="btn-primary btn-sm">Pagar</a>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ui.empty icon="file" title="Nenhuma fatura ainda" text="As faturas aparecem aqui depois que você escolhe um plano." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if (in_array($data['status'], ['trialing', 'active', 'past_due'], true) && ($data['status'] !== 'trialing' || $data['pay_url']))
        <section class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-stone-200 p-5">
            <div>
                <p class="font-semibold">Cancelar assinatura</p>
                <p class="text-sm text-stone-500">O acesso continua até o fim do período pago. Seus dados ficam guardados.</p>
            </div>
            <button type="button" wire:click="cancel" wire:confirm="Cancelar a assinatura?" class="btn-ghost text-red-600 hover:bg-red-50 hover:text-red-700">Cancelar assinatura</button>
        </section>
    @endif
</div>
