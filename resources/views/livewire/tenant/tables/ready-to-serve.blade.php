<div class="space-y-4" wire:poll.5s>
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="page-title">Prontos para servir</h1>
            <p class="mt-1 text-sm text-stone-500">Toque em <strong>Vou levar</strong> antes de ir ao balcão: assim ninguém busca o mesmo prato.</p>
        </div>
        <a href="{{ route('tables.map') }}" wire:navigate class="btn-secondary hidden sm:inline-flex"><x-icon name="table" class="size-4" /> Mesas</a>
    </div>

    @error('status') <div class="alert-error"><x-icon name="alert" class="mt-0.5 size-4" /> {{ $message }}</div> @enderror
    @if ($notice)
        <div class="alert-success" wire:key="notice-{{ md5($notice) }}">
            <x-icon name="check" class="mt-0.5 size-4" />
            <span class="flex-1">{{ $notice }}</span>
            <button type="button" wire:click="$set('notice', null)" class="-my-1 -mr-1 p-1" aria-label="Fechar aviso"><x-icon name="x" class="size-4" /></button>
        </div>
    @endif

    @if ($orders->isEmpty())
        <div class="card">
            <x-ui.empty icon="bell" title="Nada para servir agora" text="Quando a cozinha marcar um pedido de mesa como pronto, ele aparece aqui e o celular vibra." />
        </div>
    @else
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($orders as $order)
                @php
                    $command = $commands[$order->command_id] ?? null;
                    $mine = $order->runner_id === $me->id;
                    $taken = $order->runner_id && ! $mine;
                @endphp
                <article wire:key="srv-{{ $order->id }}-{{ $order->runner_id ?? 0 }}" @class(['card overflow-hidden', 'ring-2 ring-brand-500' => $mine, 'opacity-75' => $taken])>
                    <div @class(['flex items-center gap-3 border-b border-stone-100 px-4 py-3', 'bg-emerald-50' => ! $order->runner_id, 'bg-brand-50' => $mine, 'bg-stone-100' => $taken])>
                        <span @class(['flex size-12 shrink-0 items-center justify-center rounded-2xl text-lg font-extrabold text-white', 'bg-emerald-600' => ! $order->runner_id, 'bg-brand-600' => $mine, 'bg-stone-400' => $taken])>
                            {{ $command?->diningTable?->name ?? '#' }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-lg font-bold">{{ $command?->displayName() ?? 'Comanda' }}</p>
                            <p class="text-xs text-stone-600">Pedido #{{ $order->number }}@if ($order->ready_at) · pronto {{ $order->ready_at->locale('pt_BR')->diffForHumans(short: true) }}@endif</p>
                        </div>
                        @if ($mine)
                            <span class="badge-brand shrink-0">Você vai levar</span>
                        @elseif ($taken)
                            <span class="badge-gray block max-w-28 shrink-0 truncate">Com {{ \Illuminate\Support\Str::of($order->runner?->name ?? 'outro garçom')->before(' ') }}</span>
                        @endif
                    </div>
                    <ul class="space-y-2 px-4 py-3">
                        @foreach ($order->items as $item)
                            <li class="flex gap-3">
                                <span class="flex h-7 min-w-7 items-center justify-center rounded-lg bg-stone-900 px-1.5 text-sm font-bold text-white">{{ $item->quantity }}</span>
                                <span class="min-w-0 pt-0.5">
                                    <span class="block leading-tight font-semibold">{{ $item->name }}</span>
                                    @if ($item->addons->isNotEmpty())<span class="block text-sm text-stone-500">{{ $item->addons->pluck('name')->implode(', ') }}</span>@endif
                                    @if ($item->notes)<span class="block text-sm font-medium text-amber-700">Obs.: {{ $item->notes }}</span>@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="space-y-2 px-4 pb-4">
                        @if ($mine)
                            <button type="button" wire:click="serve({{ $order->id }})" wire:loading.attr="disabled" class="btn-success btn-xl">
                                <x-icon name="check" class="size-6" stroke="2.4" /> Servido
                            </button>
                            <button type="button" wire:click="release({{ $order->id }})" wire:loading.attr="disabled" class="btn-ghost w-full text-stone-500">Não vou mais levar</button>
                        @elseif ($taken)
                            <p class="rounded-xl bg-stone-100 px-3 py-2.5 text-center text-sm text-stone-600">
                                {{ $order->runner?->name ?? 'Outro garçom' }} está levando
                                @if ($order->runner_claimed_at) · há {{ $order->runner_claimed_at->locale('pt_BR')->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE, short: true) }}@endif
                            </p>
                            @if ($canOverride)
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" wire:click="release({{ $order->id }})" wire:confirm="Liberar o pedido #{{ $order->number }} para outro garçom?" class="btn-secondary">Liberar</button>
                                    <button type="button" wire:click="serve({{ $order->id }})" wire:confirm="Marcar o pedido #{{ $order->number }} como servido?" class="btn-secondary">Servido</button>
                                </div>
                            @endif
                        @else
                            <button type="button" wire:click="claim({{ $order->id }})" wire:loading.attr="disabled" class="btn-primary btn-xl">
                                <x-icon name="utensils" class="size-6" /> Vou levar
                            </button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
