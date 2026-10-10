@php($port = parse_url(config('app.url'), PHP_URL_PORT))
<div class="space-y-6">
    <x-ui.page-header title="Domínio próprio" subtitle="Use o endereço do seu restaurante (ex.: pedidos.seurestaurante.com.br) para o cardápio e o sistema." />

    @error('domain') <div class="alert-error"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $message }}</div> @enderror

    <section class="card overflow-hidden">
        <div class="card-header"><h2 class="section-title">Seus endereços</h2></div>
        <ul class="divide-y divide-stone-100">
            @foreach ($domains as $domain)
                @php($check = $checks[$domain->id] ?? null)
                <li class="space-y-3 p-5" wire:key="dom-{{ $domain->id }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-icon name="external" class="size-4 text-stone-400" />
                        <span class="font-semibold">{{ $domain->domain }}</span>
                        @if ($domain->is_primary)<span class="badge-brand">principal</span>@endif
                        @if ($domain->type === 'subdomain')
                            <span class="badge-gray">endereço do sistema</span>
                        @elseif ($domain->verified_at)
                            <span class="badge-green">verificado</span>
                        @else
                            <span class="badge-amber">aguardando DNS</span>
                        @endif
                        <div class="ml-auto flex flex-wrap gap-2">
                            @if (! $domain->is_primary && ($domain->type === 'subdomain' || $domain->verified_at))
                                <button type="button" wire:click="makePrimary({{ $domain->id }})" class="btn-ghost btn-sm">Usar como principal</button>
                            @endif
                            @if ($domain->type === 'custom')
                                @unless ($domain->verified_at)
                                    <button type="button" wire:click="verify({{ $domain->id }})" wire:loading.attr="disabled" class="btn-primary btn-sm"><x-icon name="refresh" class="size-4" /> Verificar</button>
                                @endunless
                                <button type="button" wire:click="remove({{ $domain->id }})" wire:confirm="Remover {{ $domain->domain }}?" class="btn-icon btn-ghost size-8 text-red-600 hover:bg-red-50" title="Remover"><x-icon name="trash" class="size-4" /></button>
                            @endif
                        </div>
                    </div>

                    @if ($domain->type === 'custom' && ! $domain->verified_at)
                        <div class="rounded-xl bg-stone-50 p-4 text-sm">
                            <p class="font-semibold">No painel do seu domínio (Registro.br, GoDaddy, Cloudflare...), crie dois registros:</p>
                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="text-stone-500"><tr><th class="py-1 pr-4">Tipo</th><th class="py-1 pr-4">Nome</th><th class="py-1">Valor</th></tr></thead>
                                    <tbody class="font-mono">
                                        <tr><td class="py-1 pr-4">CNAME</td><td class="py-1 pr-4">{{ $domain->domain }}</td><td class="py-1">{{ $subdomain }}</td></tr>
                                        <tr><td class="py-1 pr-4">TXT</td><td class="py-1 pr-4">{{ \App\Tenancy\CustomDomains::txtName($domain) }}</td><td class="py-1 break-all">{{ $domain->verification_token }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-3 text-xs text-stone-500">A propagação pode levar de minutos a algumas horas. Depois, clique em Verificar. O certificado HTTPS é emitido sozinho na primeira visita.</p>
                            @if ($check)
                                <p @class(['mt-2 text-xs font-semibold', 'text-emerald-700' => $check['txt_found'], 'text-amber-700' => ! $check['txt_found']])>
                                    {{ $check['txt_found'] ? 'Registro TXT encontrado.' : 'Ainda não encontramos o registro TXT.' }}
                                    {{ $check['cname'] ? 'O domínio aponta para '.$check['cname'].'.' : 'O CNAME ainda não aparece.' }}
                                </p>
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    @if ($allowed)
        <form wire:submit="add" class="card card-body flex flex-wrap items-end gap-3">
            <div class="min-w-64 flex-1">
                <label for="domain" class="label">Adicionar domínio</label>
                <input wire:model="domain" id="domain" type="text" class="input" placeholder="pedidos.seurestaurante.com.br" autocomplete="off">
            </div>
            <button type="submit" class="btn-primary"><x-icon name="plus" class="size-4" /> Adicionar</button>
        </form>
    @else
        <div class="card">
            <x-ui.empty icon="lock" title="Domínio próprio não está no seu plano" text="Disponível nos planos Profissional e Completo.">
                @can('billing.manage')<a href="{{ route('subscription') }}" class="btn-primary" wire:navigate>Ver planos</a>@endcan
            </x-ui.empty>
        </div>
    @endif
</div>
