@php
    $state = $pausedUntil ? 'paused' : ($extra ? 'busy' : 'normal');
    $leftMinutes = $pausedUntil ? max(1, (int) ceil(now()->diffInSeconds($pausedUntil) / 60)) : 0;
    $times = collect([
        $acceptsDelivery && $etaDelivery ? "entrega ~{$etaDelivery} min" : null,
        $acceptsPickup && $etaPickup ? "retirada ~{$etaPickup} min" : null,
    ])->filter()->implode(' · ');
@endphp

{{-- Hora do sufoco: a faixa muda de cor quando a previsão está aumentada ou os pedidos online pausados. --}}
<section wire:poll.30s aria-label="Hora do sufoco" @class([
    'card flex flex-col gap-3 px-4 py-3 lg:flex-row lg:items-center print:hidden',
    'border-red-200 bg-red-50' => $state === 'paused',
    'border-amber-200 bg-amber-50' => $state === 'busy',
])>
    <div class="flex min-w-0 flex-1 items-center gap-3">
        <span @class([
            'flex size-10 shrink-0 items-center justify-center rounded-xl',
            'bg-red-600 text-white' => $state === 'paused',
            'bg-amber-500 text-white' => $state === 'busy',
            'bg-stone-100 text-stone-500' => $state === 'normal',
        ])>
            <x-icon :name="$state === 'paused' ? 'pause' : 'flame'" class="size-5" />
        </span>
        <div class="min-w-0">
            <p class="font-bold leading-tight">
                @if ($state === 'paused')
                    Pedidos online pausados até {{ $pausedUntil->timezone($timezone)->format('H:i') }}
                @elseif ($state === 'busy')
                    Hora do sufoco: previsão +{{ $extra }} min
                @else
                    Movimento normal
                @endif
            </p>
            <p class="text-sm text-stone-600">
                @if ($state === 'paused')
                    O cardápio avisa o cliente e volta a receber sozinho em {{ $leftMinutes }} min.
                    @if ($extra) Previsão +{{ $extra }} min até {{ $delayUntil->timezone($timezone)->format('H:i') }}. @endif
                @elseif ($state === 'busy')
                    {{ $times ? 'Quem pedir agora vê '.$times.'.' : 'O cardápio avisa que o preparo está demorando mais.' }}
                    Volta ao normal sozinho às {{ $delayUntil->timezone($timezone)->format('H:i') }}.
                @elseif (! $acceptsOnline)
                    Os pedidos online estão desligados nas Configurações.
                @else
                    {{ $times ? 'Previsão no cardápio: '.$times.'.' : 'No pico, aumente a previsão ou pause os pedidos online por alguns minutos.' }}
                @endif
            </p>
        </div>
    </div>

    @can('orders.manage')
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <div class="flex items-center gap-1.5" role="group" aria-label="Aumentar a previsão">
                <span class="mr-1 text-xs font-semibold text-stone-500 uppercase">Previsão</span>
                @foreach (\App\Modules\Orders\RushMode::DELAY_OPTIONS as $minutes)
                    <button type="button" wire:click="delay({{ $minutes }})" wire:loading.attr="disabled"
                            @class(['btn-sm', 'btn-secondary' => $extra !== $minutes, 'btn-primary' => $extra === $minutes])
                            aria-pressed="{{ $extra === $minutes ? 'true' : 'false' }}">+{{ $minutes }}</button>
                @endforeach
                @if ($extra)
                    <button type="button" wire:click="clearDelay" wire:loading.attr="disabled" class="btn-ghost btn-sm">Normal</button>
                @endif
            </div>

            @if ($acceptsOnline)
                <div class="flex items-center gap-1.5" role="group" aria-label="Pausar pedidos online">
                    @if ($pausedUntil)
                        <button type="button" wire:click="resume" wire:loading.attr="disabled" class="btn-success btn-sm">
                            <x-icon name="play" class="size-4" /> Retomar agora
                        </button>
                    @else
                        <span class="mr-1 text-xs font-semibold text-stone-500 uppercase">Pausar online</span>
                        @foreach (\App\Modules\Orders\RushMode::PAUSE_OPTIONS as $minutes)
                            <button type="button" wire:click="pause({{ $minutes }})" wire:loading.attr="disabled" class="btn-secondary btn-sm">
                                {{ $minutes >= 60 ? ($minutes / 60).' h' : $minutes.' min' }}
                            </button>
                        @endforeach
                    @endif
                </div>
            @endif
        </div>
    @endcan
</section>
