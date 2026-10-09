@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => 'Voltar'])
{{-- Cabeçalho padrão das telas: título, subtítulo, link de volta e ações à direita. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-4']) }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" wire:navigate class="mb-1 inline-flex items-center gap-1 text-sm font-medium text-stone-500 hover:text-stone-800">
                <x-icon name="chevron-left" class="size-4" /> {{ $backLabel }}
            </a>
        @endif
        <h1 class="page-title truncate">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-stone-500">{{ $subtitle }}</p>
        @endif
    </div>
    @if (trim($slot) !== '')
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
