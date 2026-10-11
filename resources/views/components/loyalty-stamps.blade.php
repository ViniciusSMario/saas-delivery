@props(['stamps' => 0, 'goal' => 10])
{{-- Cartão fidelidade: um selo por pedido, o último é o prêmio. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-1.5']) }} role="img" aria-label="{{ $stamps }} de {{ $goal }} pedidos">
    @for ($i = 1; $i <= $goal; $i++)
        @php($done = $i <= $stamps)
        <span @class([
            'flex size-7 items-center justify-center rounded-full border-2 text-xs font-bold',
            'border-brand-600 bg-brand-600 text-white' => $done,
            'border-dashed border-stone-300 text-stone-400' => ! $done && $i < $goal,
            'border-dashed border-brand-300 bg-brand-50 text-brand-600' => ! $done && $i === $goal,
        ])>
            @if ($done)
                <x-icon name="check" class="size-4" stroke="3" />
            @elseif ($i === $goal)
                <x-icon name="gift" class="size-4" />
            @else
                {{ $i }}
            @endif
        </span>
    @endfor
</div>
