@php($closing = \App\Modules\Tables\CommandStatus::Closing)
<div class="space-y-6" wire:poll.15s>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="page-title">Mesas</h1>
            <p class="mt-1 text-sm text-stone-500">Toque numa mesa livre para abrir a comanda. Atualiza sozinho.</p>
        </div>
        <div class="flex gap-2">
            <button type="button" wire:click="startLoose" class="btn-secondary">Comanda avulsa</button>
            @can('establishment.manage')
                <a href="{{ route('tables.manage') }}" class="btn-secondary" wire:navigate>Cadastrar mesas</a>
            @endcan
        </div>
    </div>

    @unless ($hasTables)
        <div class="card card-body text-center text-stone-500">
            Nenhuma mesa cadastrada.
            @can('establishment.manage') <a href="{{ route('tables.manage') }}" class="btn-link" wire:navigate>Cadastrar mesas</a> @endcan
        </div>
    @endunless

    @foreach ($areas as $area => $tables)
        <section>
            <h2 class="text-sm font-semibold tracking-wide text-stone-500 uppercase">{{ $area }}</h2>
            <div class="mt-2 grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                @foreach ($tables as $table)
                    @php($command = $table->openCommand)
                    @if ($command)
                        <a href="{{ route('tables.command', $command) }}" wire:navigate wire:key="t-{{ $table->id }}"
                           @class(['flex aspect-square flex-col justify-between rounded-xl border-2 p-3', 'border-amber-400 bg-amber-50' => $command->status === $closing, 'border-brand-500 bg-brand-50' => $command->status !== $closing])>
                            <span class="text-2xl font-bold">{{ $table->name }}</span>
                            <span class="text-xs">
                                <span class="block font-semibold">{{ ($subtotals[$command->id] ?? \App\Support\Money::zero())->format() }}</span>
                                <span class="block text-stone-600">{{ $command->status === $closing ? 'Conta pedida' : $command->created_at->locale('pt_BR')->diffForHumans(short: true) }}</span>
                            </span>
                        </a>
                    @else
                        <button type="button" wire:click="startOpen({{ $table->id }})" wire:key="t-{{ $table->id }}"
                                class="flex aspect-square flex-col justify-between rounded-xl border-2 border-dashed border-stone-300 bg-white p-3 text-left hover:border-brand-500">
                            <span class="text-2xl font-bold text-stone-500">{{ $table->name }}</span>
                            <span class="text-xs text-stone-500">Livre · {{ $table->seats }} lugares</span>
                        </button>
                    @endif
                @endforeach
            </div>
        </section>
    @endforeach

    @if ($loose->isNotEmpty())
        <section>
            <h2 class="text-sm font-semibold tracking-wide text-stone-500 uppercase">Comandas avulsas</h2>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($loose as $command)
                    <a href="{{ route('tables.command', $command) }}" class="btn-secondary" wire:navigate wire:key="l-{{ $command->id }}">
                        {{ $command->label }} · #{{ $command->number }} · {{ ($subtotals[$command->id] ?? \App\Support\Money::zero())->format() }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($openingTableId || $openingLoose)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-stone-900/40 p-4 sm:items-center" wire:click.self="$set('openingTableId', null); $set('openingLoose', false)">
            <form wire:submit="open" class="card w-full max-w-sm space-y-4 p-5">
                <h3 class="text-lg font-semibold">{{ $openingLoose ? 'Comanda avulsa' : 'Abrir mesa' }}</h3>
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
