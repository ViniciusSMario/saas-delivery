@php
    $labels = \App\Platform\Billing\Portal::STATUS_LABELS + ['courtesy' => 'Cortesia'];
    $badges = ['trialing' => 'badge-blue', 'active' => 'badge-green', 'past_due' => 'badge-red', 'canceled' => 'badge-gray', 'courtesy' => 'badge-violet'];
    $tz = 'America/Sao_Paulo';
@endphp
<div class="space-y-6">
    <x-ui.page-header title="Assinaturas" :subtitle="'Gateway: '.config('platform.billing.driver').' · carência de '.config('platform.billing.grace_days').' dias antes de suspender.'" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Receita recorrente (MRR)" :value="$mrr->format()" icon="trending" tone="green" :hint="($counts['active'] ?? 0).' assinatura(s) ativa(s)'" />
        <x-ui.stat label="Recebido no mês" :value="$paidMonth->format()" icon="cash" tone="brand" />
        <x-ui.stat label="Em aberto" :value="$openTotal->format()" icon="file" tone="amber" :hint="($counts['past_due'] ?? 0).' inadimplente(s)'" />
        <x-ui.stat label="Em teste grátis" :value="$counts['trialing'] ?? 0" icon="clock" tone="blue" />
    </div>

    <section class="card overflow-hidden">
        <div class="card-header flex-wrap">
            <h2 class="section-title">Estabelecimentos</h2>
            <div class="flex flex-wrap gap-1">
                <button type="button" wire:click="$set('filter', '')" @class(['chip', 'chip-active' => $filter === ''])>Todos</button>
                @foreach ($labels as $key => $label)
                    <button type="button" wire:click="$set('filter', '{{ $key }}')" @class(['chip', 'chip-active' => $filter === $key])>{{ $label }} <span class="text-xs opacity-70">{{ $counts[$key] ?? 0 }}</span></button>
                @endforeach
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Estabelecimento</th><th>Plano</th><th>Assinatura</th><th>Teste até</th><th>Pago até</th><th>Acesso</th></tr></thead>
                <tbody>
                    @forelse ($tenants as $tenant)
                        @php($key = $tenant->subscription_status ?? 'courtesy')
                        <tr wire:key="t-{{ $tenant->id }}">
                            <td><a href="{{ route('platform.tenants.show', $tenant) }}" wire:navigate class="font-semibold hover:text-brand-700">{{ $tenant->name }}</a><span class="block text-xs text-stone-500">{{ $tenant->slug }}</span></td>
                            <td>{{ $tenant->plan?->name ?? '—' }}</td>
                            <td><span class="{{ $badges[$key] }}">{{ $labels[$key] }}</span></td>
                            <td class="whitespace-nowrap text-stone-600">{{ $tenant->trial_ends_at?->timezone($tz)->format('d/m/Y') ?? '—' }}</td>
                            <td class="whitespace-nowrap text-stone-600">{{ $tenant->current_period_ends_at?->timezone($tz)->format('d/m/Y') ?? '—' }}</td>
                            <td><span class="{{ $tenant->status->badge() }}">{{ $tenant->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty icon="store" title="Nenhum estabelecimento nesta situação" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="section-title">Últimas faturas</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Estabelecimento</th><th>Vencimento</th><th>Situação</th><th class="text-right">Valor</th></tr></thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->tenant?->name }}</td>
                                <td class="whitespace-nowrap">{{ $invoice->due_date->format('d/m/Y') }}</td>
                                <td><span class="{{ $invoice->statusBadge() }}">{{ $invoice->statusLabel() }}</span></td>
                                <td class="text-right font-semibold whitespace-nowrap tabular-nums">{{ $invoice->amount()->format() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-ui.empty icon="file" title="Nenhuma fatura ainda" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="section-title">Planos</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Plano</th><th class="text-right">Preço</th><th>Limites</th><th class="text-right">Assinantes</th></tr></thead>
                    <tbody>
                        @foreach ($plans as $plan)
                            <tr>
                                <td>
                                    <span class="font-semibold">{{ $plan->name }}</span>
                                    @unless ($plan->is_public)<span class="badge-gray ml-1">oculto</span>@endunless
                                </td>
                                <td class="text-right whitespace-nowrap tabular-nums">{{ $plan->price()->format() }}</td>
                                <td class="text-xs text-stone-500">{{ implode(' · ', array_slice($plan->highlights(), 0, 4)) }}</td>
                                <td class="text-right tabular-nums">{{ $plan->tenants_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-stone-100 px-5 py-3 text-xs text-stone-500">Preços e limites ficam em <code>database/seeders/Central/CentralDatabaseSeeder.php</code> (rode <code>central:migrate --seed</code>).</p>
        </section>
    </div>
</div>
