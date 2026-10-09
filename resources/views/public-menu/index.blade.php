@php
    $productImage = fn ($image) => $base.'/imagens/'.$image->id;
@endphp
<x-layouts.public :title="'Cardápio · '.$establishment->trade_name">
    {{-- Cabeçalho --}}
    <header class="bg-white">
        <div class="mx-auto flex max-w-3xl items-center gap-4 px-4 py-5">
            @if ($establishment->logo_path)
                <img src="{{ $base }}/logo" alt="Logo de {{ $establishment->trade_name }}" class="size-16 shrink-0 rounded-xl border border-stone-200 object-contain">
            @endif
            <div class="min-w-0">
                <h1 class="truncate text-xl font-semibold sm:text-2xl">{{ $establishment->trade_name }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                    <span @class(['badge', 'bg-green-100 text-green-800' => $isOpen, 'bg-stone-200 text-stone-700' => ! $isOpen])>{{ $isOpen ? 'Aberto agora' : 'Fechado agora' }}</span>
                    <span class="text-stone-500">{{ $todayLabel }}</span>
                </p>
                @if ($establishment->address())
                    <p class="mt-1 truncate text-xs text-stone-500">{{ $establishment->address() }}</p>
                @endif
            </div>
        </div>
        @if ($welcome)
            <p class="mx-auto max-w-3xl px-4 pb-4 text-sm text-stone-700">{{ $welcome }}</p>
        @endif
    </header>

    {{-- Busca e categorias --}}
    <div class="sticky top-0 z-10 border-y border-stone-200 bg-white/95 backdrop-blur">
        <div class="mx-auto max-w-3xl space-y-3 px-4 py-3">
            <input type="search" id="menu-search" placeholder="Buscar no cardápio" class="input" aria-label="Buscar no cardápio" autocomplete="off">
            @if ($categories->count() > 1 || $combos->isNotEmpty())
                <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" aria-label="Categorias">
                    @if ($combos->isNotEmpty())
                        <a href="#combos" class="shrink-0 rounded-full border border-stone-300 px-3 py-1 text-sm whitespace-nowrap hover:bg-stone-100">Combos</a>
                    @endif
                    @foreach ($categories as $category)
                        <a href="#categoria-{{ $category->id }}" class="shrink-0 rounded-full border border-stone-300 px-3 py-1 text-sm whitespace-nowrap hover:bg-stone-100">{{ $category->name }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
    </div>

    <main class="mx-auto max-w-3xl space-y-10 px-4 py-6">
        @if ($categories->isEmpty() && $combos->isEmpty())
            <div class="card card-body text-center text-stone-500">O cardápio ainda está sendo montado. Volte em breve!</div>
        @endif

        @if ($featured->isNotEmpty())
            <section data-menu-section>
                <h2 class="text-lg font-semibold">Destaques</h2>
                <div class="-mx-4 mt-3 flex gap-3 overflow-x-auto px-4 pb-2">
                    @foreach ($featured as $product)
                        <button type="button" data-open="produto-{{ $product->id }}" data-search="{{ mb_strtolower($product->name.' '.$product->description) }}"
                                class="w-40 shrink-0 overflow-hidden rounded-xl border border-stone-200 bg-white text-left">
                            @if ($product->images->first())
                                <img src="{{ $productImage($product->images->first()) }}" alt="" loading="lazy" class="aspect-square w-full object-cover">
                            @else
                                <div class="flex aspect-square w-full items-center justify-center bg-stone-100 text-3xl">🍽️</div>
                            @endif
                            <div class="p-2">
                                <p class="truncate text-sm font-medium">{{ $product->name }}</p>
                                <p class="text-sm text-stone-700">{{ $product->price_cents->format() }}</p>
                            </div>
                        </button>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($combos->isNotEmpty())
            <section id="combos" data-menu-section class="scroll-mt-32">
                <h2 class="text-lg font-semibold">Combos</h2>
                <div class="mt-3 space-y-3">
                    @foreach ($combos as $combo)
                        <article data-search="{{ mb_strtolower($combo->name.' '.$combo->description.' '.$combo->items->map(fn ($i) => $i->product?->name)->implode(' ')) }}"
                                 class="flex gap-3 rounded-xl border border-stone-200 bg-white p-3">
                            <div class="min-w-0 flex-1">
                                <h3 class="font-medium">{{ $combo->name }}</h3>
                                <p class="mt-1 text-sm text-stone-500">
                                    {{ $combo->items->map(fn ($i) => $i->quantity.'× '.$i->product?->name)->implode(' · ') }}
                                </p>
                                @if ($combo->description)
                                    <p class="mt-1 line-clamp-2 text-sm text-stone-500">{{ $combo->description }}</p>
                                @endif
                                <p class="mt-2 font-semibold">
                                    {{ $combo->price_cents->format() }}
                                    @unless ($combo->savings()->isZero())
                                        <span class="ml-1 text-xs font-medium text-green-700">economize {{ $combo->savings()->format() }}</span>
                                    @endunless
                                </p>
                            </div>
                            @if ($combo->image_path)
                                <img src="{{ $base }}/combos/{{ $combo->id }}/imagem" alt="" loading="lazy" class="size-24 shrink-0 rounded-lg object-cover">
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @foreach ($categories as $category)
            <section id="categoria-{{ $category->id }}" data-menu-section class="scroll-mt-32">
                <h2 class="text-lg font-semibold">{{ $category->name }}</h2>
                @if ($category->description)
                    <p class="text-sm text-stone-500">{{ $category->description }}</p>
                @endif

                <div class="mt-3 space-y-3">
                    @foreach ($category->products as $product)
                        <button type="button" data-open="produto-{{ $product->id }}" data-search="{{ mb_strtolower($product->name.' '.$product->description.' '.$product->code) }}"
                                @class(['flex w-full gap-3 rounded-xl border border-stone-200 bg-white p-3 text-left transition hover:border-stone-300', 'opacity-60' => ! $product->is_available])>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-medium">{{ $product->name }}</h3>
                                @if ($product->description)
                                    <p class="mt-1 line-clamp-2 text-sm text-stone-500">{{ $product->description }}</p>
                                @endif
                                <p class="mt-2 font-semibold">
                                    {{ $product->addonGroups->contains(fn ($g) => $g->isRequired()) ? 'A partir de ' : '' }}{{ $product->price_cents->format() }}
                                    @unless ($product->is_available)
                                        <span class="badge ml-1 bg-stone-200 text-stone-700">Esgotado</span>
                                    @endunless
                                </p>
                            </div>
                            @if ($product->images->first())
                                <img src="{{ $productImage($product->images->first()) }}" alt="" loading="lazy" class="size-24 shrink-0 rounded-lg object-cover">
                            @endif
                        </button>

                        {{-- Detalhe do produto --}}
                        <dialog id="produto-{{ $product->id }}" class="m-auto w-full max-w-lg rounded-2xl p-0 backdrop:bg-stone-900/50">
                            <div class="max-h-[85vh] overflow-y-auto">
                                @if ($product->images->isNotEmpty())
                                    <div class="flex snap-x snap-mandatory overflow-x-auto">
                                        @foreach ($product->images as $image)
                                            <img src="{{ $productImage($image) }}" alt="{{ $product->name }}" loading="lazy" class="aspect-[4/3] w-full shrink-0 snap-center object-cover">
                                        @endforeach
                                    </div>
                                @endif
                                <div class="space-y-4 p-5">
                                    <div>
                                        <h3 class="text-lg font-semibold">{{ $product->name }}</h3>
                                        <p class="mt-1 font-semibold text-stone-800">{{ $product->price_cents->format() }}</p>
                                        @if ($product->description)
                                            <p class="mt-2 text-sm whitespace-pre-line text-stone-600">{{ $product->description }}</p>
                                        @endif
                                        @unless ($product->is_available)
                                            <p class="mt-2 text-sm font-medium text-stone-700">Esgotado no momento.</p>
                                        @endunless
                                    </div>

                                    @foreach ($product->addonGroups as $group)
                                        @if ($group->addons->isNotEmpty())
                                            <div class="rounded-lg bg-stone-50 p-3">
                                                <p class="text-sm font-semibold">
                                                    {{ $group->name }}
                                                    <span class="ml-1 text-xs font-normal text-stone-500">{{ $group->rule() }}{{ $group->isRequired() ? ' · obrigatório' : '' }}</span>
                                                </p>
                                                <ul class="mt-2 divide-y divide-stone-200 text-sm">
                                                    @foreach ($group->addons as $addon)
                                                        <li class="flex justify-between py-1.5">
                                                            <span>{{ $addon->name }}</span>
                                                            <span class="text-stone-600">{{ $addon->price_cents->isZero() ? 'sem custo' : '+ '.$addon->price_cents->format() }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    @endforeach

                                    <form method="dialog">
                                        <button class="btn-secondary w-full">Fechar</button>
                                    </form>
                                    <p class="text-center text-xs text-stone-400">Pedidos online chegam em breve.</p>
                                </div>
                            </div>
                        </dialog>
                    @endforeach
                </div>
            </section>
        @endforeach

        <p id="menu-empty" class="hidden text-center text-sm text-stone-500">Nada encontrado para essa busca.</p>

        {{-- Informações --}}
        <footer class="grid gap-4 border-t border-stone-200 pt-6 text-sm sm:grid-cols-2">
            <div>
                <h2 class="font-semibold">Horários</h2>
                <dl class="mt-2 space-y-1">
                    @foreach ($week as $day)
                        <div class="flex justify-between"><dt class="text-stone-500">{{ $day['label'] }}</dt><dd>{{ $day['hours'] }}</dd></div>
                    @endforeach
                </dl>
            </div>
            <div class="space-y-1">
                <h2 class="font-semibold">Informações</h2>
                @if ($acceptsDelivery)
                    <p>Entrega{{ $deliveryFee->isZero() ? ' grátis' : ': taxa de '.$deliveryFee->format() }}</p>
                @endif
                @if ($acceptsPickup)
                    <p>Retirada no local</p>
                @endif
                @unless ($minOrder->isZero())
                    <p>Pedido mínimo: {{ $minOrder->format() }}</p>
                @endunless
                @if ($establishment->phone)
                    <p>Telefone: <a href="tel:{{ preg_replace('/\D/', '', $establishment->phone) }}" class="text-brand-700 hover:underline">{{ $establishment->phone }}</a></p>
                @endif
            </div>
        </footer>
    </main>

    <script>
        // Abrir detalhes (dialog nativo) e busca local, sem ida ao servidor.
        document.querySelectorAll('[data-open]').forEach((el) => {
            el.addEventListener('click', () => document.getElementById(el.dataset.open)?.showModal());
        });
        document.querySelectorAll('dialog').forEach((dialog) => {
            dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });
        });

        const normalize = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
        document.getElementById('menu-search')?.addEventListener('input', (e) => {
            const term = normalize(e.target.value.trim());
            let visible = 0;
            document.querySelectorAll('[data-menu-section]').forEach((section) => {
                let shown = 0;
                section.querySelectorAll('[data-search]').forEach((item) => {
                    const match = term === '' || normalize(item.dataset.search).includes(term);
                    item.classList.toggle('hidden', ! match);
                    shown += match ? 1 : 0;
                });
                section.classList.toggle('hidden', shown === 0);
                visible += shown;
            });
            document.getElementById('menu-empty').classList.toggle('hidden', visible > 0);
        });
    </script>
</x-layouts.public>
