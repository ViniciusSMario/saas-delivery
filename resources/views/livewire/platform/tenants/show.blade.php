@php($port = parse_url(config('app.url'), PHP_URL_PORT))
<div class="space-y-6">
    <div>
        <a href="{{ route('platform.tenants.index') }}" class="btn-link" wire:navigate>← Estabelecimentos</a>
        <h1 class="page-title mt-1">{{ $tenant->name }}</h1>
    </div>

    @error('status') <div class="alert-error">{{ $message }}</div> @enderror

    <section class="card card-body">
        <dl class="grid gap-4 text-sm sm:grid-cols-3">
            <div><dt class="text-stone-500">Status</dt><dd class="font-medium">{{ $tenant->status->value }}</dd></div>
            <div><dt class="text-stone-500">Slug</dt><dd class="font-medium">{{ $tenant->slug }}</dd></div>
            <div><dt class="text-stone-500">CNPJ</dt><dd class="font-medium">{{ $tenant->document ?? '—' }}</dd></div>
            <div><dt class="text-stone-500">Schema</dt><dd class="font-mono text-xs">{{ $tenant->schema_name }}</dd></div>
            <div><dt class="text-stone-500">Versão do schema</dt><dd class="font-mono text-xs">{{ $tenant->schema_version ?? '—' }}</dd></div>
            <div><dt class="text-stone-500">Plano</dt><dd class="font-medium">{{ $tenant->plan?->name ?? '—' }}</dd></div>
            <div class="sm:col-span-3">
                <dt class="text-stone-500">Domínios</dt>
                <dd>
                    @foreach ($tenant->domains as $domain)
                        <a href="http://{{ $domain->domain }}{{ $port ? ':'.$port : '' }}" target="_blank" class="mr-3 font-medium text-brand-700 hover:underline">{{ $domain->domain }}</a>
                    @endforeach
                </dd>
            </div>
        </dl>
    </section>

    @can('platform.manage-tenants')
        <section class="card card-body">
            <h2 class="font-semibold">Situação</h2>
            @if ($tenant->status->value === 'active')
                <p class="mt-1 text-sm text-stone-500">Suspender bloqueia o app e o cardápio; nenhum dado é apagado.</p>
                <div class="mt-4 flex flex-wrap items-end gap-3">
                    <div class="min-w-64 flex-1">
                        <label for="reason" class="label">Motivo (opcional)</label>
                        <input wire:model="reason" id="reason" type="text" class="input" placeholder="Ex.: inadimplência">
                    </div>
                    <button type="button" wire:click="suspend" wire:confirm="Suspender {{ $tenant->name }}?" class="btn-danger">Suspender</button>
                </div>
            @elseif ($tenant->status->value === 'suspended')
                <p class="mt-1 text-sm text-stone-500">Suspenso desde {{ $tenant->suspended_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}.</p>
                <button type="button" wire:click="reactivate" wire:confirm="Reativar {{ $tenant->name }}?" class="btn-primary mt-4">Reativar</button>
            @else
                <p class="mt-1 text-sm text-stone-500">Nenhuma ação disponível para este status.</p>
            @endif
        </section>
    @endcan

    <section class="card overflow-x-auto">
        <h2 class="px-5 pt-5 font-semibold sm:px-6">Eventos</h2>
        <table class="table mt-3">
            <thead class="bg-stone-50">
                <tr><th>Quando</th><th>Evento</th><th>Quem</th><th>Detalhes</th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($events as $event)
                    <tr wire:key="event-{{ $event->id }}">
                        <td class="whitespace-nowrap text-stone-500">{{ $event->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</td>
                        <td class="font-medium">{{ $event->type }}</td>
                        <td>{{ $event->actor ?? '—' }}</td>
                        <td class="max-w-md truncate text-xs text-stone-500">{{ $event->payload ? json_encode($event->payload, JSON_UNESCAPED_UNICODE) : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</div>
