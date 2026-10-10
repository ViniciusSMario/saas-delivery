<nav wire:poll.10s="refresh" x-data @field-alert.window="navigator.vibrate?.([90, 60, 90])"
     class="print:hidden fixed inset-x-0 bottom-0 z-30 border-t border-stone-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur" aria-label="Navegação">
    <div class="mx-auto flex max-w-lg">
        @foreach ($tabs as $tab)
            @php($active = in_array($current, $tab['match'], true))
            <a href="{{ route($tab['route']) }}" wire:navigate wire:key="tab-{{ $tab['route'] }}"
               @class(['relative flex min-h-16 flex-1 flex-col items-center justify-center gap-1 text-xs font-semibold transition active:bg-stone-100', 'text-brand-700' => $active, 'text-stone-500' => ! $active])
               @if ($active) aria-current="page" @endif>
                <span @class(['flex h-8 w-14 items-center justify-center rounded-full transition', 'bg-brand-100' => $active])>
                    <x-icon :name="$tab['icon']" class="size-6" />
                </span>
                {{ $tab['label'] }}
                @if ($tab['badge'] > 0)
                    <span class="absolute top-1.5 left-1/2 ml-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[11px] font-bold text-white ring-2 ring-white">{{ $tab['badge'] }}</span>
                @endif
            </a>
        @endforeach
    </div>
</nav>
