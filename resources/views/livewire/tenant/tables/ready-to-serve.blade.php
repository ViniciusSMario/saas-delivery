<div class="space-y-4" wire:poll.10s>
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="page-title">Prontos para servir</h1>
            <p class="mt-1 text-sm text-stone-500">A cozinha terminou. Leve à mesa e toque em <strong>Servido</strong>.</p>
        </div>
        <a href="{{ route('tables.map') }}" wire:navigate class="btn-secondary hidden sm:inline-flex"><x-icon name="table" class="size-4" /> Mesas</a>
    </div>

    @error('status') <div class="alert-error"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $message }}</div> @enderror

    @if ($orders->isEmpty())
        <div class="card">
            <x-ui.empty icon="bell" title="Nada para servir agora" text="Quando a cozinha marcar um pedido de mesa como pronto, ele aparece aqui e o celular vibra." />
        </div>
    @else
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($orders as $order)
                @php($command = $commands[$order->command_id] ?? null)
                <article wire:key="srv-{{ $order->id }}" class="card overflow-hidden">
                    <div class="flex items-center gap-3 border-b border-stone-100 bg-emerald-50 px-4 py-3">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-lg font-extrabold text-white">
                            {{ $command?->diningTable?->name ?? '#' }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-lg font-bold">{{ $command?->displayName() ?? 'Comanda' }}</p>
                            <p class="text-xs text-emerald-800">Pedido #{{ $order->number }} · pronto {{ $order->updated_at->locale('pt_BR')->diffForHumans(short: true) }}</p>
                        </div>
                    </div>
                    <ul class="space-y-2 px-4 py-3">
                        @foreach ($order->items as $item)
                            <li class="flex gap-3">
                                <span class="flex h-7 min-w-7 items-center justify-center rounded-lg bg-stone-900 px-1.5 text-sm font-bold text-white">{{ $item->quantity }}</span>
                                <span class="min-w-0 pt-0.5">
                                    <span class="block font-semibold leading-tight">{{ $item->name }}</span>
                                    @if ($item->addons->isNotEmpty())<span class="block text-sm text-stone-500">{{ $item->addons->pluck('name')->implode(', ') }}</span>@endif
                                    @if ($item->notes)<span class="block text-sm font-medium text-amber-700">Obs.: {{ $item->notes }}</span>@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="px-4 pb-4">
                        <button type="button" wire:click="serve({{ $order->id }})" wire:loading.attr="disabled" class="btn-success btn-xl">
                            <x-icon name="check" class="size-6" stroke="2.4" /> Servido
                        </button>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
