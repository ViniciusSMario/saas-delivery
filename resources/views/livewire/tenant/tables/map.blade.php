@php
    $closing = \App\Modules\Tables\CommandStatus::Closing;
    $filters = [
        'all' => ['Todas', array_sum($counts), null],
        'free' => ['Livres', $counts['free'], 'bg-stone-300'],
        'busy' => ['Ocupadas', $counts['busy'], 'bg-brand-500'],
        'closing' => ['Conta pedida', $counts['closing'], 'bg-amber-400'],
    ];
@endphp
<div class="space-y-5" wire:poll.15s>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <h1 class="page-title">Mesas</h1>
            <p class="mt-1 text-sm text-stone-500">Toque numa mesa livre para abrir. Atualiza sozinho.</p>
        </div>
        <div class="flex gap-2">
            <button type="button" wire:click="startLoose" class="btn-secondary"><x-icon name="plus" class="size-4" /> Comanda avulsa</button>
            @can('establishment.manage')
                <a href="{{ route('tables.manage') }}" class="btn-secondary hidden sm:inline-flex" wire:navigate><x-icon name="sliders" class="size-4" /> Cadastrar mesas</a>
            @endcan
        </div>
    </div>

    @if ($toServe > 0)
        <a href="{{ route('tables.ready') }}" wire:navigate class="flex items-center gap-3 rounded-2xl bg-emerald-600 px-4 py-3 text-white shadow-sm active:bg-emerald-700">
            <span class="flex size-10 items-center justify-center rounded-xl bg-white/20"><x-icon name="bell" class="size-5" /></span>
            <span class="flex-1 font-semibold">{{ $toServe }} {{ $toServe === 1 ? 'pedido pronto' : 'pedidos prontos' }} para servir</span>
            <x-icon name="chevron-right" class="size-5" />
        </a>
    @endif

    @if ($hasTables)
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 scrollbar-none sm:mx-0 sm:px-0">
            @foreach ($filters as $key => [$name, $count, $dot])
                <button type="button" wire:click="setFilter('{{ $key }}')" @class(['chip h-10', 'chip-active' => $filter === $key])>
                    @if ($dot)<span class="size-2.5 rounded-full {{ $dot }}"></span>@endif
                    {{ $name }} <strong>{{ $count }}</strong>
                </button>
            @endforeach
        </div>
    @else
        <div class="card">
            <x-ui.empty icon="table" title="Nenhuma mesa cadastrada" text="Cadastre as mesas do salão para abrir comandas por aqui.">
                @can('establishment.manage') <a href="{{ route('tables.manage') }}" class="btn-primary" wire:navigate>Cadastrar mesas</a> @endcan
            </x-ui.empty>
        </div>
    @endif

    @if ($hasTables && $areas->isEmpty())
        <div class="card"><x-ui.empty icon="table" title="Nenhuma mesa neste filtro" /></div>
    @endif

    @foreach ($areas as $area => $tables)
        <section>
            @if ($areas->count() > 1)<h2 class="stat-label mb-2">{{ $area }}</h2>@endif
            <div class="grid grid-cols-3 gap-2.5 sm:grid-cols-[repeat(auto-fill,minmax(9.5rem,1fr))] sm:gap-3">
                @foreach ($tables as $table)
                    @php($command = $table->openCommand)
                    @if ($command)
                        @php($isClosing = $command->status === $closing)
                        <a href="{{ route('tables.command', $command) }}" wire:navigate wire:key="t-{{ $table->id }}"
                           @class([
                               'relative flex aspect-square flex-col justify-between overflow-hidden rounded-2xl p-3 text-white shadow-(--shadow-card) transition active:scale-[0.97] sm:aspect-[4/3] sm:p-4',
                               'bg-gradient-to-br from-amber-400 to-amber-500' => $isClosing,
                               'bg-gradient-to-br from-brand-500 to-brand-700' => ! $isClosing,
                           ])>
                            <span class="flex w-full items-start justify-between gap-1">
                                <span class="text-3xl leading-none font-extrabold tracking-tight">{{ $table->name }}</span>
                                @if ($isClosing)<x-icon name="file" class="size-5 shrink-0" />@endif
                            </span>
                            <span class="w-full min-w-0">
                                <span class="block truncate text-sm font-bold tabular-nums sm:text-lg">{{ ($subtotals[$command->id] ?? \App\Support\Money::zero())->format() }}</span>
                                <span class="flex items-center gap-1 text-[11px] text-white/85 sm:text-xs">
                                    <x-icon name="clock" class="size-3.5" /> {{ $command->created_at->locale('pt_BR')->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}
                                    @if ($command->people)<span class="hidden min-[400px]:inline-flex items-center gap-0.5">· <x-icon name="users" class="size-3.5" /> {{ $command->people }}</span>@endif
                                </span>
                            </span>
                        </a>
                    @else
                        <button type="button" wire:click="startOpen({{ $table->id }})" wire:key="t-{{ $table->id }}"
                                class="group flex aspect-square flex-col justify-between rounded-2xl border-2 border-dashed border-stone-300 bg-white p-3 text-left transition active:scale-[0.97] active:border-brand-400 sm:aspect-[4/3] sm:p-4 sm:hover:border-brand-400">
                            <span class="flex w-full items-start justify-between">
                                <span class="text-3xl leading-none font-extrabold tracking-tight text-stone-400">{{ $table->name }}</span>
                                <x-icon name="plus" class="size-5 text-stone-300" />
                            </span>
                            <span class="flex w-full items-center justify-between gap-1 text-xs font-medium text-stone-500">
                                Livre
                                <span class="inline-flex items-center gap-1" title="{{ $table->seats }} lugares"><x-icon name="users" class="size-3.5" /> {{ $table->seats }}</span>
                            </span>
                        </button>
                    @endif
                @endforeach
            </div>
        </section>
    @endforeach

    @if ($loose->isNotEmpty())
        <section>
            <h2 class="stat-label mb-2">Comandas avulsas</h2>
            <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($loose as $command)
                    <a href="{{ route('tables.command', $command) }}" class="card flex min-w-0 items-center gap-3 p-4 transition active:scale-[0.99]" wire:navigate wire:key="l-{{ $command->id }}">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="orders" class="size-5" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ $command->label }}</span>
                            <span class="block text-xs text-stone-500">Comanda #{{ $command->number }}</span>
                        </span>
                        <span class="font-bold tabular-nums">{{ ($subtotals[$command->id] ?? \App\Support\Money::zero())->format() }}</span>
                        <x-icon name="chevron-right" class="size-5 text-stone-300" />
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Abrir mesa / comanda avulsa: folha que sobe de baixo no celular. --}}
    @if ($openingTableId || $openingLoose)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-stone-950/50 backdrop-blur-sm sm:items-center sm:p-4" wire:click.self="cancelOpen">
            <form wire:submit="open" x-data="{ people: $wire.entangle('people') }"
                  class="w-full max-w-sm space-y-5 rounded-t-3xl bg-white p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-2xl sm:rounded-3xl sm:p-6">
                <div class="mx-auto h-1.5 w-10 rounded-full bg-stone-200 sm:hidden"></div>
                <div class="flex items-center gap-3">
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-brand-600 text-xl font-extrabold text-white">
                        @if ($opening) {{ $opening->name }} @else <x-icon name="orders" class="size-6" /> @endif
                    </span>
                    <div>
                        <h3 class="text-xl font-bold">{{ $openingLoose ? 'Comanda avulsa' : 'Abrir mesa '.$opening?->name }}</h3>
                        @if ($opening)<p class="text-sm text-stone-500">{{ $opening->seats }} lugares</p>@endif
                    </div>
                </div>

                @if ($openingLoose)
                    <div>
                        <label for="label" class="label">Nome ou número</label>
                        <input wire:model="label" id="label" type="text" maxlength="40" class="input h-12" placeholder="Ex.: Cartão 15, João" autocomplete="off">
                    </div>
                @endif

                <div>
                    <p class="label">Quantas pessoas?</p>
                    <div class="flex items-center justify-between gap-3">
                        <button type="button" @click="people = Math.max(1, people - 1)" class="flex size-14 items-center justify-center rounded-2xl border-2 border-stone-200 text-stone-700 active:bg-stone-100" aria-label="Menos uma pessoa">
                            <x-icon name="minus" class="size-6" stroke="2.4" />
                        </button>
                        <span class="text-5xl font-extrabold tabular-nums" x-text="people">{{ $people }}</span>
                        <button type="button" @click="people = Math.min(99, people + 1)" class="flex size-14 items-center justify-center rounded-2xl border-2 border-stone-200 text-stone-700 active:bg-stone-100" aria-label="Mais uma pessoa">
                            <x-icon name="plus" class="size-6" stroke="2.4" />
                        </button>
                    </div>
                    <div class="mt-3 grid grid-cols-6 gap-1.5">
                        @foreach (range(1, 6) as $n)
                            <button type="button" @click="people = {{ $n }}" :class="people === {{ $n }} ? 'bg-stone-900 text-white border-stone-900' : 'border-stone-200 text-stone-700'" class="h-10 rounded-xl border text-sm font-semibold">{{ $n }}</button>
                        @endforeach
                    </div>
                </div>

                @error('open') <p class="field-error">{{ $message }}</p> @enderror

                <div class="grid grid-cols-[auto_1fr] gap-2">
                    <button type="button" wire:click="cancelOpen" class="btn-secondary h-14 rounded-2xl px-4">Cancelar</button>
                    <button type="submit" class="btn-primary btn-xl" wire:loading.attr="disabled"><x-icon name="check" class="size-5" stroke="2.4" /> Abrir e lançar</button>
                </div>
            </form>
        </div>
    @endif
</div>
