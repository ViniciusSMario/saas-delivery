@php
    $modes = [
        'open' => ['Só mesas abertas pelo garçom', 'O garçom abre a mesa e o cliente pede pelo celular. Evita pedido de quem não está no salão (foto do QR, por exemplo).'],
        'any' => ['O cliente abre a mesa', 'O primeiro pedido pelo celular já abre a comanda, dentro do horário de funcionamento.'],
        'off' => ['Só ver o cardápio', 'O QR mostra o cardápio com preços, mas os pedidos são com o garçom.'],
    ];
@endphp
<div class="space-y-6">
    <x-ui.page-header title="QR codes das mesas" subtitle="Cole na mesa: o cliente aponta a câmera, vê o cardápio, pede, chama o garçom e pede a conta pelo celular." class="print:hidden">
        <a href="{{ route('tables.manage') }}" class="btn-secondary" wire:navigate><x-icon name="sliders" class="size-4" /> Cadastro de mesas</a>
        <button type="button" onclick="window.print()" class="btn-primary"><x-icon name="printer" class="size-4" /> Imprimir</button>
    </x-ui.page-header>

    <section class="card card-body space-y-3 print:hidden">
        <div class="flex items-center justify-between gap-3">
            <h2 class="section-title">Pedido pelo celular</h2>
            @if ($saved)<span class="text-sm font-medium text-emerald-700"><x-icon name="check" class="inline size-4" /> Salvo</span>@endif
        </div>
        <div class="grid gap-2 md:grid-cols-3">
            @foreach ($modes as $value => [$label, $text])
                <label @class(['flex cursor-pointer gap-3 rounded-xl border-2 p-3', 'border-brand-600 bg-brand-50' => $mode === $value, 'border-stone-200' => $mode !== $value])>
                    <input type="radio" wire:model.live="mode" value="{{ $value }}" class="mt-1">
                    <span class="text-sm"><strong class="block">{{ $label }}</strong><span class="text-stone-600">{{ $text }}</span></span>
                </label>
            @endforeach
        </div>
        <p class="hint">Os pedidos entram na comanda da mesa, vão para a cozinha e para a impressão como os do garçom, e o garçom recebe um aviso no celular. "Chamar garçom" e "Pedir a conta" aparecem no mapa de mesas.</p>
    </section>

    @if ($tables->isEmpty())
        <div class="card"><x-ui.empty icon="table" title="Nenhuma mesa cadastrada" text="Cadastre as mesas para gerar os QR codes." /></div>
    @endif

    {{-- Um cartão por mesa; na impressão, 2 por linha (A4). --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 print:grid-cols-2 print:gap-6">
        @foreach ($tables as $table)
            <article wire:key="qr-{{ $table->id }}" class="card flex flex-col items-center gap-3 p-6 text-center break-inside-avoid print:border-2 print:border-stone-300 print:shadow-none">
                <p class="text-sm font-semibold text-stone-500">{{ $name }}</p>
                <p class="text-4xl font-extrabold tracking-tight">Mesa {{ $table->name }}</p>
                <div class="size-48 [&_svg]:size-full">{!! $codes[$table->id]['svg'] !!}</div>
                <p class="text-sm font-medium text-stone-700">Aponte a câmera para ver o cardápio e pedir</p>
                <div class="flex w-full items-center gap-2 border-t border-stone-100 pt-3 print:hidden">
                    <a href="{{ $codes[$table->id]['url'] }}" target="_blank" class="btn-link min-w-0 flex-1 truncate text-left text-xs">{{ $codes[$table->id]['url'] }}</a>
                    <button type="button" wire:click="renew({{ $table->id }})" wire:confirm="Gerar um QR novo para a mesa {{ $table->name }}? O QR impresso hoje para de funcionar." class="btn-ghost btn-sm shrink-0" title="Gerar QR novo">
                        <x-icon name="refresh" class="size-4" /> Novo QR
                    </button>
                </div>
            </article>
        @endforeach
    </div>
</div>
