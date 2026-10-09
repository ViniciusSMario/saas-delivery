@php
    $statusStyle = [
        'active' => 'bg-green-100 text-green-800',
        'suspended' => 'bg-amber-100 text-amber-800',
        'failed' => 'bg-red-100 text-red-700',
        'provisioning' => 'bg-sky-100 text-sky-800',
    ];
    $statusLabel = [
        'provisioning' => 'Provisionando', 'active' => 'Ativo', 'suspended' => 'Suspenso',
        'pending_deletion' => 'Exclusão pendente', 'deleted' => 'Excluído', 'failed' => 'Falhou', 'restoring' => 'Restaurando',
    ];
    $port = parse_url(config('app.url'), PHP_URL_PORT);
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="page-title">Estabelecimentos</h1>
            <p class="mt-1 text-sm text-stone-500">
                {{ $counts['active'] ?? 0 }} ativos · {{ $counts['suspended'] ?? 0 }} suspensos · {{ $counts->sum() }} no total
            </p>
        </div>
        @can('platform.manage-tenants')
            <a href="{{ route('platform.tenants.create') }}" class="btn-primary" wire:navigate>Novo estabelecimento</a>
        @endcan
    </div>

    <div class="flex flex-wrap gap-3">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar por nome, slug ou CNPJ" class="input max-w-xs" aria-label="Buscar">
        <select wire:model.live="status" class="input w-auto" aria-label="Status">
            <option value="">Todos os status</option>
            @foreach ($statuses as $s)
                <option value="{{ $s->value }}">{{ $statusLabel[$s->value] }}</option>
            @endforeach
        </select>
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead class="bg-stone-50">
                <tr>
                    <th>Estabelecimento</th>
                    <th>Status</th>
                    <th>Criado em</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($tenants as $tenant)
                    @php($domain = $tenant->domains->first()?->domain)
                    <tr wire:key="tenant-{{ $tenant->id }}">
                        <td>
                            <a href="{{ route('platform.tenants.show', $tenant) }}" class="font-medium text-stone-900 hover:underline" wire:navigate>{{ $tenant->name }}</a>
                            <p class="text-xs text-stone-500">
                                {{ $tenant->slug }}
                                @if ($domain)
                                    · <a href="http://{{ $domain }}{{ $port ? ':'.$port : '' }}" target="_blank" class="hover:underline">{{ $domain }}</a>
                                @endif
                            </p>
                        </td>
                        <td><span class="badge {{ $statusStyle[$tenant->status->value] ?? 'bg-stone-100 text-stone-700' }}">{{ $statusLabel[$tenant->status->value] }}</span></td>
                        <td class="text-stone-500">{{ $tenant->created_at->timezone('America/Sao_Paulo')->format('d/m/Y') }}</td>
                        <td class="text-right whitespace-nowrap">
                            @can('platform.manage-tenants')
                                @if ($tenant->status->value === 'active')
                                    <button type="button" wire:click="suspend('{{ $tenant->id }}')" wire:confirm="Suspender {{ $tenant->name }}? O app e o cardápio ficam bloqueados até a reativação." class="btn-link text-amber-700">Suspender</button>
                                @elseif ($tenant->status->value === 'suspended')
                                    <button type="button" wire:click="reactivate('{{ $tenant->id }}')" wire:confirm="Reativar {{ $tenant->name }}?" class="btn-link">Reativar</button>
                                @endif
                            @endcan
                            <a href="{{ route('platform.tenants.show', $tenant) }}" class="btn-link ml-3" wire:navigate>Detalhes</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-stone-500">Nenhum estabelecimento encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $tenants->links() }}
</div>
