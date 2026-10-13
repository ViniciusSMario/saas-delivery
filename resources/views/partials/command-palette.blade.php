{{-- Busca rápida (Ctrl+K): telas, ações e registros. Comportamento em resources/js/palette.js. --}}
@php
    $paletteUser = auth()->user();
    $palettePages = \App\Support\PanelMenu::pagesFor($paletteUser);
    $paletteActions = \App\Support\PanelMenu::actionsFor($paletteUser);
    $paletteIcons = collect([...$palettePages, ...$paletteActions])->pluck('icon')
        ->merge(['orders', 'user', 'book', 'box', 'info'])
        ->unique()
        ->mapWithKeys(fn ($name) => [$name => \App\Support\Icons::path($name)]);
    $paletteData = ['pages' => $palettePages, 'actions' => $paletteActions, 'icons' => $paletteIcons, 'url' => route('quick-search')];
@endphp
<div x-data="commandPalette" @open-palette.window="show()" @keydown.escape.window="hide()" class="print:hidden"
     :data-hotkeys-modal="open ? '' : null">
    <script type="application/json" x-ref="data">{!! json_encode($paletteData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>

    <div x-show="open" x-cloak class="fixed inset-0 z-[90] flex items-start justify-center p-3 pt-[8vh] sm:p-6 sm:pt-[12vh]" role="dialog" aria-modal="true" aria-label="Busca rápida">
        <div class="absolute inset-0 bg-stone-950/50 backdrop-blur-sm" x-show="open" x-transition.opacity @click="hide()"></div>

        <div class="relative flex max-h-[75vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl" x-show="open"
             x-transition:enter="transition duration-150" x-transition:enter-start="scale-95 opacity-0" x-transition:enter-end="scale-100 opacity-100">
            <label class="flex items-center gap-3 border-b border-stone-200 px-4">
                <x-icon name="search" class="size-5 text-stone-400" />
                <input x-ref="input" x-model="query" @input="search()" type="text" role="searchbox" autocomplete="off" spellcheck="false" enterkeyhint="go"
                       @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="go(items[active])"
                       class="h-14 min-w-0 flex-1 bg-transparent text-base text-stone-900 placeholder:text-stone-400 focus:outline-none"
                       placeholder="Buscar tela, pedido (#123), cliente ou produto" aria-label="Buscar">
                <span x-show="loading" class="size-4 animate-spin rounded-full border-2 border-stone-300 border-r-transparent" aria-hidden="true"></span>
                <span class="kbd text-stone-500">Esc</span>
            </label>

            <div x-ref="list" class="overflow-y-auto overscroll-contain p-2">
                <template x-for="group in groups" :key="group.name">
                    <div class="pb-1">
                        <p class="px-3 pt-2 pb-1 text-[11px] font-semibold tracking-wider text-stone-500 uppercase" x-text="group.name"></p>
                        <template x-for="item in group.items" :key="item.index + item.url">
                            <a :href="item.url" :data-index="item.index" @click.prevent="go(item)" @mousemove="active = item.index"
                               :class="active === item.index ? 'bg-brand-50 text-brand-900' : 'text-stone-700'"
                               class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm">
                                <span :class="active === item.index ? 'bg-brand-100 text-brand-700' : 'bg-stone-100 text-stone-500'" class="flex size-8 shrink-0 items-center justify-center rounded-lg">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="icon(item.icon)" /></svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium" x-text="item.label"></span>
                                    <span x-show="item.subtitle" class="block truncate text-xs text-stone-500" x-text="item.subtitle"></span>
                                </span>
                                <span x-show="item.external" class="text-xs text-stone-400">nova aba</span>
                                <x-icon name="chevron-right" class="size-4 text-stone-300" x-show="active === item.index" />
                            </a>
                        </template>
                    </div>
                </template>

                <p x-show="! items.length && ! loading" class="px-3 py-10 text-center text-sm text-stone-500">Nada encontrado para “<span x-text="query"></span>”.</p>
            </div>

            <div class="hidden items-center gap-4 border-t border-stone-100 bg-stone-50 px-4 py-2 text-xs text-stone-500 [@media(pointer:fine)]:flex">
                <span class="flex items-center gap-1"><span class="kbd">↑</span><span class="kbd">↓</span> escolher</span>
                <span class="flex items-center gap-1"><span class="kbd">Enter</span> abrir</span>
                <span class="ml-auto flex items-center gap-1"><span class="kbd">?</span> todos os atalhos</span>
            </div>
        </div>
    </div>
</div>
