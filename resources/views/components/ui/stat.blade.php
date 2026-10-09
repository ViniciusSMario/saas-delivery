@props(['label', 'value', 'icon' => null, 'tone' => 'brand', 'hint' => null, 'trend' => null, 'href' => null])
@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600',
        'green' => 'bg-emerald-50 text-emerald-600',
        'blue' => 'bg-sky-50 text-sky-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'violet' => 'bg-violet-50 text-violet-600',
        'red' => 'bg-red-50 text-red-600',
        'gray' => 'bg-stone-100 text-stone-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp
{{-- Cartão de indicador: rótulo, valor grande, ícone e variação opcional. --}}
<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->merge(['class' => 'card card-body flex items-start gap-4'.($href ? ' transition hover:border-brand-300 hover:shadow-md' : '')]) }}>
    @if ($icon)
        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl {{ $tones[$tone] ?? $tones['brand'] }}">
            <x-icon :name="$icon" class="size-5" />
        </span>
    @endif
    <div class="min-w-0">
        <p class="stat-label">{{ $label }}</p>
        <p class="stat-value truncate">{{ $value }}</p>
        @if ($trend !== null)
            <p @class(['mt-0.5 text-xs font-semibold', 'text-emerald-600' => $trend >= 0, 'text-red-600' => $trend < 0])>
                {{ $trend >= 0 ? '▲' : '▼' }} {{ abs($trend) }}% <span class="font-normal text-stone-500">{{ $hint }}</span>
            </p>
        @elseif ($hint)
            <p class="mt-0.5 text-xs text-stone-500">{{ $hint }}</p>
        @endif
        {{ $slot }}
    </div>
</{{ $tag }}>
