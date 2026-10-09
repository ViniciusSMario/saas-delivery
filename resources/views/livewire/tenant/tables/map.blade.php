@php
    $closing = \App\Modules\Tables\CommandStatus::Closing;
    $all = $areas->flatten();
    $busy = $all->filter(fn ($t) => $t->openCommand)->count();
    $billing = $all->filter(fn ($t) => $t->openCommand?->status === $closing)->count();
@endphp
<div class="space-y-6" wire:poll.15s>
    <x-ui.page-header title="Mesas" subtitle="Toque numa mesa livre para abrir a comanda. Atualiza sozinho.">
        <button type="button" wire:click="startLoose" class="btn-secondary"><x-icon name="plus" class="size-4" /> Comanda avulsa</button>
        @can('establishment.manage')
            <a href="{{ route('tables.manage') }}" class="btn-secondary" wire:navigate><x-icon name="sliders" class="size-4" /> Cadastrar mesas</a>
        @endcan
    </x-ui.page-header>

    @if ($hasTables)
        <div class="flex flex-wrap gap-2 text-sm">
            <span class="chip"><span class="size-2.5 rounded-full bg-stone-300"></span> Livres <strong>{{ $all->count() - $busy }}</strong></span>
            <span class="chip"><span class="size-2.5 rounded-full bg-brand-500"></span> Ocupadas <strong>{{ $busy - $billing }}</strong></span>
            <span class="chip"><span class="size-2.5 rounded-full bg-amber-400"></span> Conta pedida <strong>{{ $billing }}</strong></span>
        </div>
    @else
        <div class="card">
            <x-ui.empty icon="table" title="Nenhuma mesa cadastrada" text="Cadastre as mesas do salão para abrir comandas por aqui.">
                @can('establishment.manage') <a href="{{ route('tables.manage') }}" class="btn-primary" wire:navigate>Cadastrar mesas</a> @endcan
            </x-ui.empty>
        </div>
    @endif

    @foreach ($areas as $area => $tables)
        <section>
            <h2 class="stat-label">{{ $area }}</h2>
            <div class="mt-3 grid grid-cols-[repeat(auto-fill,minmax(9.5rem,1fr))] gap-3">
                @foreach ($tables as $table)
                    @php($command = $table->openCommand)
                    @if ($command)
                        @php($isClosing = $command->status === $closing)
                        <a href="{{ route('tables.command', $command) }}" wire:navigate wire:key="t-{{ $table->id }}"
                           @class([
                               'group relative flex aspect-[4/3] flex-col justify-between overflow-hidden rounded-2xl p-4 text-white shadow-(--shadow-card) transition hover:-translate-y-0.5 hover:shadow-lg',
                               'bg-gradient-to-br from-amber-400 to-amber-500' => $isClosing,
                               'bg-gradient-to-br from-brand-500 to-brand-700' => ! $isClosing,
                           ])>
                            <span class="flex items-start justify-between gap-2">
                                <span class="text-3xl font-extrabold tracking-tight">{{ $table->name }}</span>
                                <span class="rounded-full bg-white/20 px-2 py-0.5 text-[11px] font-semibold">{{ $isClosing ? 'Conta pedida' : 'Ocupada' }}</span>
                            </span>
                            <span>
                                <span class="block text-lg font-bold tabular-nums">{{ ($subtotals[$command->id] ?? \App\Support\Money::zero())->format() }}</span>
                                <span class="flex items-center gap-1 text-xs text-white/80">
                                    <x-icon name="clock" class="size-3.5" /> {{ $command->created_at->locale('pt_BR')->diffForHumans(short: true) }}
                                    @if ($command->people) · <x-icon name="users" class="size-3.5" /> {{ $command->people }} @endif
                                </span>
                            </span>
                        </a>
                    @else
                        <button type="button" wire:click="startOpen({{ $table->id }})" wire:key="t-{{ $table->id }}"
                                class="group flex aspect-[4/3] flex-col justify-between rounded-2xl border-2 border-dashed border-stone-300 bg-white p-4 text-left transition hover:border-brand-400 hover:bg-brand-50/40">
                            <span class="flex items-start justify-between">
                                <span class="text-3xl font-extrabold tracking-tight text-stone-400 group-hover:text-brand-600">{{ $table->name }}</span>
                                <x-icon name="plus" class="size-5 text-stone-300 group-hover:text-brand-500" />
                            </span>
                            <span class="flex items-center justify-between gap-1 text-xs font-medium text-stone-500">
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
            <h2 class="stat-label">Comandas avulsas</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($loose as $command)
                    <a href="{{ route('tables.command', $command) }}" class="card flex items-center gap-3 p-4 transition hover:border-brand-300 hover:shadow-md" wire:navigate wire:key="l-{{ $command->id }}">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="orders" class="size-5" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ $command->label }}</span>
                            <span class="block text-xs text-stone-500">Comanda #{{ $command->number }}</span>
                        </span>
                        <span class="font-bold tabular-nums">{{ ($subtotals[$command->id] ?? \App\Support\Money::zero())->format() }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($openingTableId || $openingLoose)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-stone-950/50 p-4 backdrop-blur-sm sm:items-center" wire:click.self="$set('openingTableId', null); $set('openingLoose', false)">
            <form wire:submit="open" class="card w-full max-w-sm space-y-4 p-6 shadow-2xl">
                <div class="flex items-center gap-3">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="table" class="size-5" /></span>
                    <h3 class="text-lg font-bold">{{ $openingLoose ? 'Comanda avulsa' : 'Abrir mesa' }}</h3>
                </div>
                @if ($openingLoose)
                    <div>
                        <label for="label" class="label">Nome ou número (ex.: Cartão 15, João)</label>
                        <input wire:model="label" id="label" type="text" maxlength="40" class="input" autofocus>
                    </div>
                @endif
                <div>
                    <label for="people" class="label">Pessoas</label>
                    <input wire:model="people" id="people" type="number" min="1" max="99" class="input">
                </div>
                @error('open') <p class="field-error">{{ $message }}</p> @enderror
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('openingTableId', null); $set('openingLoose', false)" class="btn-secondary">Cancelar</button>
                    <button type="submit" class="btn-primary">Abrir comanda</button>
                </div>
            </form>
        </div>
    @endif
</div>
