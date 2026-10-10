@php
    use App\Tenancy\Enums\TenantStatus;

    $port = parse_url(config('app.url'), PHP_URL_PORT);
    $tz = 'America/Sao_Paulo';
    $operable = in_array($tenant->status, [TenantStatus::Active, TenantStatus::Suspended], true);
@endphp
<div class="space-y-6">
    <x-ui.page-header :title="$tenant->name" :back="route('platform.tenants.index')" back-label="Estabelecimentos">
        <x-slot:subtitle>
            <span class="{{ $tenant->status->badge() }}">{{ $tenant->status->label() }}</span>
            <span class="ml-1">{{ $tenant->slug }}</span>
        </x-slot:subtitle>
    </x-ui.page-header>

    @error('status') <div class="alert-error"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $message }}</div> @enderror
    @error('backup') <div class="alert-error"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $message }}</div> @enderror

    <section class="card card-body">
        <dl class="grid gap-4 text-sm sm:grid-cols-3">
            <div><dt class="stat-label">CNPJ</dt><dd class="mt-1 font-medium">{{ $tenant->document ?? '—' }}</dd></div>
            <div><dt class="stat-label">Plano</dt><dd class="mt-1 font-medium">{{ $tenant->plan?->name ?? '—' }}</dd></div>
            <div><dt class="stat-label">Criado em</dt><dd class="mt-1 font-medium">{{ $tenant->created_at->timezone($tz)->format('d/m/Y') }}</dd></div>
            <div><dt class="stat-label">Schema</dt><dd class="mt-1 font-mono text-xs">{{ $tenant->schema_name }}</dd></div>
            <div><dt class="stat-label">Versão do schema</dt><dd class="mt-1 font-mono text-xs">{{ $tenant->schema_version ?? '—' }}</dd></div>
            <div>
                <dt class="stat-label">Domínios</dt>
                <dd class="mt-1">
                    @forelse ($tenant->domains as $domain)
                        <a href="http://{{ $domain->domain }}{{ $port ? ':'.$port : '' }}" target="_blank" class="mr-3 font-medium text-brand-700 hover:underline">{{ $domain->domain }}</a>
                    @empty
                        —
                    @endforelse
                </dd>
            </div>
        </dl>
    </section>

    @can('platform.manage-tenants')
        {{-- Situação --}}
        @if (in_array($tenant->status, [TenantStatus::Active, TenantStatus::Suspended], true))
            <section class="card card-body">
                <h2 class="section-title">Situação</h2>
                @if ($tenant->status === TenantStatus::Active)
                    <p class="mt-1 text-sm text-stone-500">Suspender bloqueia o app e o cardápio; nenhum dado é apagado.</p>
                    <div class="mt-4 flex flex-wrap items-end gap-3">
                        <div class="min-w-64 flex-1">
                            <label for="reason" class="label">Motivo (opcional)</label>
                            <input wire:model="reason" id="reason" type="text" class="input" placeholder="Ex.: inadimplência">
                        </div>
                        <button type="button" wire:click="suspend" wire:confirm="Suspender {{ $tenant->name }}?" class="btn-danger">Suspender</button>
                    </div>
                @else
                    <p class="mt-1 text-sm text-stone-500">
                        Suspenso desde {{ $tenant->suspended_at?->timezone($tz)->format('d/m/Y H:i') }}{{ $tenant->suspension_reason === 'billing' ? ' por falta de pagamento (reativa sozinho quando a fatura for paga)' : '' }}.
                    </p>
                    <button type="button" wire:click="reactivate" wire:confirm="Reativar {{ $tenant->name }}?" class="btn-primary mt-4">Reativar</button>
                @endif
            </section>
        @endif
    @endcan

    {{-- Assinatura --}}
    @php
        $subLabels = \App\Platform\Billing\Portal::STATUS_LABELS;
        $subBadges = ['trialing' => 'badge-blue', 'active' => 'badge-green', 'past_due' => 'badge-red', 'canceled' => 'badge-gray'];
    @endphp
    <section class="card overflow-hidden">
        <div class="card-header">
            <h2 class="section-title">Assinatura</h2>
            <span class="{{ $subBadges[$tenant->subscription_status] ?? 'badge-violet' }}">{{ $subLabels[$tenant->subscription_status] ?? 'Cortesia (sem cobrança)' }}</span>
        </div>
        <div class="grid gap-4 p-5 text-sm sm:grid-cols-4 sm:p-6">
            <div><p class="stat-label">Teste até</p><p class="mt-1 font-medium">{{ $tenant->trial_ends_at?->timezone($tz)->format('d/m/Y') ?? '—' }}</p></div>
            <div><p class="stat-label">Pago até</p><p class="mt-1 font-medium">{{ $tenant->current_period_ends_at?->timezone($tz)->format('d/m/Y') ?? '—' }}</p></div>
            <div><p class="stat-label">E-mail de cobrança</p><p class="mt-1 truncate font-medium">{{ $tenant->billing_email ?? '—' }}</p></div>
            <div><p class="stat-label">Assinatura no gateway</p><p class="mt-1 truncate font-mono text-xs">{{ $tenant->billing_subscription_id ?? '—' }}</p></div>
        </div>
        @if ($invoices->isNotEmpty())
            <div class="overflow-x-auto border-t border-stone-100">
                <table class="table">
                    <thead><tr><th>Vencimento</th><th>Descrição</th><th>Situação</th><th class="text-right">Valor</th></tr></thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td class="whitespace-nowrap">{{ $invoice->due_date->format('d/m/Y') }}</td>
                                <td class="text-stone-600">{{ $invoice->description }}</td>
                                <td><span class="{{ $invoice->statusBadge() }}">{{ $invoice->statusLabel() }}</span></td>
                                <td class="text-right font-semibold tabular-nums">{{ $invoice->amount()->format() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @can('platform.manage-tenants')
            @if ($operable)
                <div class="flex flex-wrap items-end gap-3 border-t border-stone-100 bg-stone-50/60 p-5 sm:px-6">
                    <div>
                        <label for="planId" class="label">Plano</label>
                        <div class="flex gap-2">
                            <select wire:model="planId" id="planId" class="input w-auto">
                                <option value="" disabled>Escolha</option>
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->id }}">{{ $plan->name }} · {{ $plan->price()->format() }}</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="setPlan" class="btn-secondary">Trocar</button>
                        </div>
                    </div>
                    <button type="button" wire:click="extendTrial" wire:confirm="Estender o teste grátis de {{ $tenant->name }} por 7 dias?" class="btn-secondary">+7 dias de teste</button>
                    @if ($tenant->subscription_status)
                        <button type="button" wire:click="makeCourtesy" wire:confirm="Tirar {{ $tenant->name }} da cobrança (cortesia)? Faturas em aberto são canceladas." class="btn-ghost">Cortesia (sem cobrança)</button>
                    @endif
                </div>
            @endif
        @endcan
    </section>

    {{-- Backups --}}
    <section class="card overflow-hidden">
        <div class="card-header">
            <div>
                <h2 class="section-title">Backups</h2>
                <p class="text-xs text-stone-500">Diário às 03:00. Ficam {{ config('platform.backups.keep_daily') }} diários, o de domingo das últimas {{ config('platform.backups.keep_weekly') }} semanas e os demais por {{ config('platform.backups.keep_other_days') }} dias.</p>
            </div>
            @can('platform.manage-tenants')
                @if ($operable)
                    <button type="button" wire:click="backupNow" wire:loading.attr="disabled" class="btn-secondary">
                        <x-icon name="download" class="size-4" />
                        <span wire:loading.remove wire:target="backupNow">Fazer backup agora</span>
                        <span wire:loading wire:target="backupNow">Fazendo backup...</span>
                    </button>
                @endif
            @endcan
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Data</th><th>Tipo</th><th>Situação</th><th class="text-right">Tamanho</th><th>Por</th><th class="text-right">Ações</th></tr></thead>
                <tbody>
                    @forelse ($backups as $backup)
                        <tr wire:key="backup-{{ $backup->id }}">
                            <td class="whitespace-nowrap">{{ $backup->created_at->timezone($tz)->format('d/m/Y H:i') }}</td>
                            <td><span @class(['badge-gray' => $backup->kind === 'daily', 'badge-blue' => $backup->kind === 'manual', 'badge-amber' => $backup->kind === 'pre_restore', 'badge-violet' => $backup->kind === 'final'])>{{ $backup->kindLabel() }}</span></td>
                            <td>
                                @if ($backup->status === 'done') <span class="badge-green">Concluído</span>
                                    @if ($backup->offsite_at)<span class="badge-blue ml-1" title="Cópia fora do servidor em {{ $backup->offsite_at->timezone($tz)->format('d/m H:i') }}">no bucket</span>@endif
                                @elseif ($backup->status === 'running') <span class="badge-amber">Em andamento</span>
                                @else <span class="badge-red" title="{{ $backup->error }}">Falhou</span> @endif
                            </td>
                            <td class="text-right whitespace-nowrap tabular-nums">{{ $backup->status === 'done' ? $backup->humanSize() : '—' }}</td>
                            <td class="text-xs text-stone-500">{{ $backup->created_by ?? 'agendado' }}</td>
                            <td class="text-right whitespace-nowrap">
                                @can('platform.manage-tenants')
                                    @if ($backup->status === 'done')
                                        <a href="{{ route('platform.backups.download', $backup) }}" class="btn-ghost btn-sm" title="Baixar .zip"><x-icon name="download" class="size-4" /> Baixar</a>
                                        @if ($operable)
                                            <button type="button" wire:click="restore({{ $backup->id }})"
                                                    wire:confirm="Restaurar {{ $tenant->name }} para {{ $backup->created_at->timezone($tz)->format('d/m/Y H:i') }}? O app fica em manutenção por alguns instantes e o estado atual vira um backup &quot;antes da restauração&quot;."
                                                    class="btn-ghost btn-sm text-amber-700 hover:bg-amber-50"><x-icon name="refresh" class="size-4" /> Restaurar</button>
                                        @endif
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty icon="download" title="Nenhum backup ainda" text="O primeiro backup diário roda às 03:00." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Exclusão --}}
    @can('platform.manage-tenants')
        @if ($operable)
            <section class="card border-red-200">
                <div class="card-header border-red-100 bg-red-50/50"><h2 class="section-title text-red-800">Excluir estabelecimento</h2></div>
                <div class="space-y-3 p-5 sm:p-6">
                    <p class="text-sm text-stone-600">
                        O acesso é bloqueado na hora e o dono recebe um e-mail. Os dados são apagados de vez em {{ config('platform.deletion_grace_days') }} dias (com um backup final);
                        até lá dá para cancelar.
                    </p>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-64">
                            <label for="confirmSlug" class="label">Digite <span class="font-mono">{{ $tenant->slug }}</span> para confirmar</label>
                            <input wire:model="confirmSlug" id="confirmSlug" type="text" class="input" autocomplete="off">
                            @error('confirmSlug') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <button type="button" wire:click="scheduleDeletion" class="btn-danger"><x-icon name="trash" class="size-4" /> Agendar exclusão</button>
                    </div>
                </div>
            </section>
        @elseif ($tenant->status === TenantStatus::PendingDeletion)
            <section class="alert-warning items-center">
                <x-icon name="alert" class="size-5" />
                <span class="flex-1">Exclusão agendada para <strong>{{ $tenant->deletion_scheduled_at?->timezone($tz)->format('d/m/Y') }}</strong>. Até lá o acesso fica bloqueado.</span>
                <button type="button" wire:click="cancelDeletion" wire:confirm="Cancelar a exclusão de {{ $tenant->name }}?" class="btn-secondary btn-sm">Cancelar exclusão</button>
            </section>
        @endif
    @endcan

    <section class="card overflow-hidden">
        <div class="card-header"><h2 class="section-title">Eventos</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr><th>Quando</th><th>Evento</th><th>Quem</th><th>Detalhes</th></tr>
                </thead>
                <tbody>
                    @foreach ($events as $event)
                        <tr wire:key="event-{{ $event->id }}">
                            <td class="whitespace-nowrap text-stone-500">{{ $event->created_at->timezone($tz)->format('d/m/Y H:i') }}</td>
                            <td class="font-medium">{{ $event->type }}</td>
                            <td>{{ $event->actor ?? '—' }}</td>
                            <td class="max-w-md truncate text-xs text-stone-500">{{ $event->payload ? json_encode($event->payload, JSON_UNESCAPED_UNICODE) : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
