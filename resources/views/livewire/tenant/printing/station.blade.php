@php($browserStations = $stations->where('mode', 'browser'))
<div class="space-y-6"
     x-data="{
         running: false,
         busy: false,
         timer: null,
         printed: 0,
         start() {
             this.running = true;
             this.tick();
             this.timer = setInterval(() => this.tick(), 4000);
         },
         stop() {
             this.running = false;
             clearInterval(this.timer);
         },
         async tick() {
             if (this.busy || ! this.running) return;
             this.busy = true;
             try {
                 const result = await $wire.claim();
                 if (result.ids.length) {
                     $refs.paper.innerHTML = result.html;
                     await new Promise((r) => setTimeout(r, 150));
                     window.print();
                     await $wire.confirm(result.ids);
                     this.printed += result.ids.length;
                 }
             } finally {
                 this.busy = false;
             }
         },
     }"
     x-init="if (@js(count($stationIds) > 0)) start()">

    <x-ui.page-header title="Estação de impressão" subtitle="Deixe esta tela aberta no computador da impressora: os cupons dos setores marcados saem sozinhos.">
        <a href="{{ route('printing.settings') }}" class="btn-secondary" wire:navigate><x-icon name="sliders" class="size-4" /> Configurar impressoras</a>
    </x-ui.page-header>

    @if ($browserStations->isEmpty())
        <div class="card">
            <x-ui.empty icon="printer" title="Nenhum setor com impressão pelo navegador" text="Cadastre os setores (cozinha, bar, expedição) e escolha o modo “estação de impressão”.">
                <a href="{{ route('printing.settings') }}" class="btn-primary" wire:navigate>Configurar impressoras</a>
            </x-ui.empty>
        </div>
    @else
        <section class="card card-body space-y-4">
            <div>
                <h2 class="section-title">Setores que este computador imprime</h2>
                <p class="text-sm text-stone-500">A escolha fica no endereço desta página: salve nos favoritos do computador da cozinha.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($browserStations as $station)
                    <button type="button" wire:click="toggleStation({{ $station->id }})" @class(['chip', 'chip-active' => in_array($station->id, $stationIds, true)])>
                        <x-icon name="printer" class="size-4" /> {{ $station->name }}
                    </button>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center gap-3 rounded-2xl border p-4" :class="running ? 'border-emerald-200 bg-emerald-50' : 'border-stone-200 bg-stone-50'">
                <span class="flex size-10 items-center justify-center rounded-xl" :class="running ? 'bg-emerald-600 text-white' : 'bg-stone-200 text-stone-500'">
                    <x-icon name="printer" class="size-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold" x-text="running ? 'Imprimindo automaticamente' : 'Parada'"></p>
                    <p class="text-sm text-stone-500">
                        {{ $pending }} na fila · <span x-text="printed"></span> impresso(s) nesta sessão
                    </p>
                </div>
                <button type="button" x-show="! running" @click="start()" class="btn-success" @disabled(! $stationIds)><x-icon name="check" class="size-4" /> Começar</button>
                <button type="button" x-show="running" x-cloak @click="stop()" class="btn-secondary">Parar</button>
            </div>

            <details class="text-sm text-stone-600">
                <summary class="cursor-pointer font-semibold text-stone-800">Imprimir sem a janela de impressão (recomendado)</summary>
                <ol class="mt-2 list-decimal space-y-1 pl-5">
                    <li>Defina a impressora térmica como padrão no Windows.</li>
                    <li>Crie um atalho do Chrome com <code class="rounded bg-stone-100 px-1">--kiosk-printing</code> no final do destino.</li>
                    <li>Abra esta página por esse atalho e clique em Começar.</li>
                </ol>
            </details>
        </section>

        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="section-title">Últimos cupons</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Cupom</th><th>Setor</th><th>Situação</th><th>Quando</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($recent as $job)
                            <tr wire:key="job-{{ $job->id }}">
                                <td class="font-semibold">{{ $job->title }}</td>
                                <td>{{ $job->station->name }}</td>
                                <td>
                                    @switch($job->status)
                                        @case('printed') <span class="badge-green">Impresso</span> @break
                                        @case('printing') <span class="badge-amber">Imprimindo</span> @break
                                        @case('failed') <span class="badge-red" title="{{ $job->error }}">Falhou</span> @break
                                        @default <span class="badge-gray">Na fila</span>
                                    @endswitch
                                </td>
                                <td class="text-stone-500">{{ $job->created_at->timezone('America/Sao_Paulo')->format('H:i') }}</td>
                                <td class="text-right">
                                    @if (in_array($job->status, ['printed', 'failed'], true))
                                        <button type="button" wire:click="reprint({{ $job->id }})" class="btn-ghost btn-sm"><x-icon name="printer" class="size-4" /> Reimprimir</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-ui.empty icon="printer" title="Nenhum cupom ainda" text="Os pedidos novos aparecem aqui." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Área impressa: só ela aparece no papel. --}}
    <div id="print-paper" x-ref="paper" wire:ignore aria-hidden="true"></div>

    <style>
        #print-paper { display: none; }
        /* 1,5 mm por coluna: 48 colunas = 72 mm (papel 80 mm), 32 = 48 mm (papel 58 mm). */
        .ticket { font-family: 'Courier New', ui-monospace, monospace; font-size: 2.5mm; line-height: 1.25; color: #000; width: calc(var(--cols) * 1.5mm); }
        .ticket-line { white-space: pre-wrap; word-break: break-word; }
        .ticket-bold { font-weight: 700; }
        .ticket-big { font-weight: 700; font-size: 5mm; line-height: 1.15; }
        .ticket-center { text-align: center; }
        @media print {
            @page { size: 80mm auto; margin: 2mm; }
            body * { visibility: hidden !important; }
            #print-paper, #print-paper * { visibility: visible !important; }
            #print-paper { display: block; position: absolute; left: 0; top: 0; }
            .ticket { page-break-after: always; }
        }
    </style>
</div>
