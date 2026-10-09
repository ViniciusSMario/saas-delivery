@props(['icon' => 'info', 'title', 'text' => null])
{{-- Estado vazio: ícone, título, explicação e ação opcional. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
    <span class="flex size-12 items-center justify-center rounded-2xl bg-stone-100 text-stone-500">
        <x-icon :name="$icon" class="size-6" />
    </span>
    <p class="mt-3 font-semibold text-stone-900">{{ $title }}</p>
    @if ($text)<p class="mt-1 max-w-sm text-sm text-stone-500">{{ $text }}</p>@endif
    @if (trim($slot) !== '')<div class="mt-4">{{ $slot }}</div>@endif
</div>
