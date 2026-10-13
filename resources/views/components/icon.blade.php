@props(['name', 'stroke' => '1.8'])
{{-- Ícones de traço (24×24): os caminhos ficam em App\Support\Icons. <x-icon name="home" class="size-5" /> --}}
<svg {{ $attributes->merge(['class' => 'size-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="{{ \App\Support\Icons::path($name) }}" />
</svg>
