@php
    $productImage = fn ($image) => $base.'/imagens/'.$image->id;
    // Na mesa (QR code), o carrinho fecha no pedido da mesa; no cardápio normal, no checkout de entrega/retirada.
    $checkoutUrl = $table ? $tableUrl.'/pedido' : $base.'/pedido';
    $name = $establishment->trade_name ?: tenant()->name;
    $digits = preg_replace('/\D/', '', (string) $establishment->phone);
    $whatsapp = strlen($digits) >= 10 ? 'https://wa.me/'.(strlen($digits) <= 11 ? '55' : '').$digits : null;
    $mapsUrl = $establishment->address() ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($establishment->address()) : null;
    $combosCatalog = $combos->map(fn ($combo) => [
        'id' => $combo->id,
        'name' => $combo->name,
        'price' => $combo->price_cents->cents,
        'items' => $combo->items->map(fn ($i) => $i->quantity.'× '.$i->product?->name)->implode(' · '),
        'description' => $combo->description,
        'image' => $combo->image_path ? $base.'/combos/'.$combo->id.'/imagem' : null,
    ])->values();
@endphp
<x-layouts.public :title="'Cardápio · '.$name">
    <x-slot:head>
        <link rel="manifest" href="{{ $base }}/manifest.webmanifest">
        @if ($establishment->logo_path)
            <link rel="apple-touch-icon" href="{{ $base }}/logo">
        @endif
    </x-slot:head>

    <div x-data="menuApp()" class="pb-40">
        {{-- ============ ABA CARDÁPIO ============ --}}
        <div x-show="tab === 'cardapio'">
            {{-- Capa e cartão do estabelecimento --}}
            <header class="relative">
                <div @class(['relative overflow-hidden bg-gradient-to-br from-brand-500 via-brand-600 to-brand-800 pt-safe', 'h-52' => $establishment->cover_path, 'h-36' => ! $establishment->cover_path])>
                    @if ($establishment->cover_path)
                        {{-- Foto de capa; o degradê embaixo mantém os botões e o cartão legíveis. --}}
                        <img src="{{ $base }}/capa?v={{ substr(md5($establishment->cover_path), 0, 8) }}" alt="" class="absolute inset-0 size-full object-cover" fetchpriority="high">
                        <div class="absolute inset-0 bg-gradient-to-b from-black/35 via-transparent to-black/40"></div>
                    @else
                        <div class="absolute inset-0 opacity-20 [background-image:radial-gradient(circle_at_1px_1px,white_1px,transparent_0)] [background-size:18px_18px]"></div>
                    @endif
                    <div class="relative flex justify-end gap-2 p-3">
                        <button type="button" @click="share()" class="flex size-10 items-center justify-center rounded-full bg-black/20 text-white backdrop-blur hover:bg-black/30" aria-label="Compartilhar cardápio">
                            <x-icon name="share" class="size-5" />
                        </button>
                        <a href="#loja" class="flex size-10 items-center justify-center rounded-full bg-black/20 text-white backdrop-blur hover:bg-black/30" aria-label="Informações da loja">
                            <x-icon name="info" class="size-5" />
                        </a>
                    </div>
                </div>

                <div class="relative -mt-14 px-4">
                    <div class="card p-4">
                        <div class="flex items-start gap-3">
                            @if ($establishment->logo_path)
                                <img src="{{ $base }}/logo" alt="Logo de {{ $name }}" class="-mt-10 size-20 shrink-0 rounded-2xl border-4 border-white bg-white object-contain shadow-md">
                            @else
                                <span class="-mt-10 flex size-20 shrink-0 items-center justify-center rounded-2xl border-4 border-white bg-brand-600 text-3xl font-bold text-white shadow-md">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <h1 class="text-xl leading-tight font-extrabold tracking-tight">{{ $name }}</h1>
                                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                    <span @class(['badge', 'badge-green' => $isOpen, 'badge-gray' => ! $isOpen])>
                                        <span @class(['size-1.5 rounded-full', 'bg-emerald-500 animate-pulse' => $isOpen, 'bg-stone-400' => ! $isOpen])></span>
                                        {{ $isOpen ? 'Aberto agora' : 'Fechado agora' }}
                                    </span>
                                    <span class="text-stone-500">{{ $todayLabel }}</span>
                                </p>
                            </div>
                        </div>

                        @if ($establishment->address())
                            <p class="mt-3 flex items-center gap-1.5 text-xs text-stone-500">
                                <x-icon name="pin" class="size-4 text-stone-400" />
                                <span class="truncate">{{ $establishment->address() }}</span>
                            </p>
                        @endif

                        <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium text-stone-700">
                            @if ($table)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-600 px-3 py-1 font-bold text-white">
                                    <x-icon name="table" class="size-4" /> Mesa {{ $table->name }}
                                </span>
                            @else
                            @if ($acceptsDelivery)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2.5 py-1">
                                    <x-icon name="truck" class="size-4 text-brand-600" />
                                    @if ($zones->isNotEmpty())
                                        Entrega {{ $deliveryFee->isZero() ? 'grátis em alguns bairros' : 'a partir de '.$deliveryFee->format() }}
                                    @else
                                        {{ $deliveryFee->isZero() ? 'Entrega grátis' : 'Entrega '.$deliveryFee->format() }}
                                    @endif
                                    @if ($etaDelivery)<span class="text-stone-500">· ~{{ $etaDelivery }} min</span>@endif
                                </span>
                            @endif
                            @if ($acceptsPickup)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2.5 py-1">
                                    <x-icon name="bag" class="size-4 text-brand-600" /> Retirada no local
                                    @if ($etaPickup)<span class="text-stone-500">· ~{{ $etaPickup }} min</span>@endif
                                </span>
                            @endif
                            @unless ($minOrder->isZero())
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2.5 py-1">
                                    <x-icon name="cash" class="size-4 text-brand-600" /> Mínimo {{ $minOrder->format() }}
                                </span>
                            @endunless
                            @endif
                        </div>

                        @if ($welcome)
                            <p class="mt-3 border-t border-stone-100 pt-3 text-sm text-stone-600">{{ $welcome }}</p>
                        @endif
                    </div>

                    {{-- Cupons divulgados: "Usar" guarda o código, que o checkout já aplica. --}}
                    @if (! $table && $ordering && $coupons->isNotEmpty())
                        <div @class(['mt-3 space-y-2', '-mx-4 flex snap-x gap-3 space-y-0 overflow-x-auto px-4 scroll-px-4 scrollbar-none' => $coupons->count() > 1])>
                            @foreach ($coupons as $coupon)
                                <div x-data="{ saved: false }" @class(['flex items-center gap-3 rounded-2xl border-2 border-dashed border-brand-300 bg-brand-50 p-3', 'w-[85%] shrink-0 snap-start' => $coupons->count() > 1])>
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-white"><x-icon name="ticket" class="size-5" /></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm leading-snug font-bold">{{ $coupon->benefit() }}</p>
                                        <p class="text-xs text-stone-600">
                                            Cupom <strong class="tracking-wide">{{ $coupon->code }}</strong>@if ($conditions = $coupon->conditions($timezone)) · {{ $conditions }}@endif
                                        </p>
                                    </div>
                                    <button type="button" class="btn-primary btn-sm shrink-0 rounded-full" :class="saved && 'bg-emerald-600 hover:bg-emerald-600'"
                                            @click="saveCoupon(@js($coupon->code)); saved = true; $store.cart.notify(@js('Cupom '.$coupon->code.' guardado: ele entra no fechamento do pedido'))">
                                        <span x-text="saved ? 'Guardado' : 'Usar'">Usar</span>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Cartão fidelidade: só o convite (o progresso aparece na página de cada pedido). --}}
                    @if ($loyaltyHeadline && $ordering)
                        <div class="mt-3 flex items-center gap-3 rounded-2xl bg-white p-3 shadow-xs ring-1 ring-stone-200">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="star" class="size-5" /></span>
                            <p class="min-w-0 text-sm leading-snug"><strong class="block">Cartão fidelidade</strong> <span class="text-stone-600">{{ $loyaltyHeadline }}. Conta sozinho pelo seu telefone.</span></p>
                        </div>
                    @endif

                    @if ($table)
                        @if ($ordering)
                            <div class="mt-3 flex items-center gap-3 rounded-2xl bg-white p-3 shadow-xs ring-1 ring-stone-200">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="qr" class="size-5" /></span>
                                <p class="min-w-0 text-sm leading-snug"><strong class="block">Peça por aqui</strong> <span class="text-stone-600">Seu pedido vai direto para a cozinha e entra na conta da mesa {{ $table->name }}.</span></p>
                            </div>
                        @else
                            <div class="alert-warning mt-3"><x-icon name="info" class="mt-0.5 size-4" /> <span>{{ $tableState['reason'] }}</span></div>
                        @endif
                    @elseif ($pausedUntil && ! $ordering)
                        {{-- Hora do sufoco: pausa curta, com o horário em que os pedidos voltam. --}}
                        <div class="alert-warning mt-3">
                            <x-icon name="pause" class="mt-0.5 size-4" />
                            <span>Estamos com muitos pedidos e pausamos os pedidos online por alguns minutos. <strong>Voltamos às {{ $pausedUntil->timezone($timezone)->format('H:i') }}.</strong></span>
                        </div>
                    @elseif (! $ordering)
                        <div class="alert-warning mt-3">
                            <x-icon name="clock" class="mt-0.5 size-4" />
                            <span>{{ $isOpen ? 'Pedidos online indisponíveis no momento.' : 'Estamos fechados agora. Você pode ver o cardápio; os pedidos voltam no próximo horário de funcionamento.' }}</span>
                        </div>
                    @elseif ($rushMinutes)
                        <div class="alert-warning mt-3">
                            <x-icon name="flame" class="mt-0.5 size-4" />
                            <span>Estamos com muitos pedidos agora e o preparo está levando um pouco mais.
                                @if ($etaDelivery && $acceptsDelivery) Entrega em cerca de <strong>{{ $etaDelivery }} min</strong>@if ($etaPickup && $acceptsPickup), retirada em cerca de <strong>{{ $etaPickup }} min</strong>@endif.
                                @elseif ($etaPickup && $acceptsPickup) Retirada em cerca de <strong>{{ $etaPickup }} min</strong>.
                                @endif
                            </span>
                        </div>
                    @endif
                </div>
            </header>

            {{-- Barra fixa no topo: busca, carrinho e categorias (acompanham a rolagem) --}}
            <div x-ref="menuTop" class="h-4"></div>
            @if ($categories->isNotEmpty() || $combos->isNotEmpty())
                <div x-ref="sticky" class="sticky top-0 z-20 border-b border-stone-200/70 bg-stone-50/95 backdrop-blur-lg">
                    <div class="flex items-center gap-2 px-4 pt-3" :class="term.trim() && 'pb-3'">
                        <label class="relative min-w-0 flex-1">
                            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-stone-400" />
                            <input x-model.debounce.150ms="term" type="search" enterkeyhint="search" placeholder="Buscar no cardápio" autocomplete="off"
                                   class="input h-11 rounded-2xl bg-white pl-11 text-base" aria-label="Buscar no cardápio">
                        </label>
                        <button type="button" x-show="term" x-cloak @click="term = ''" class="shrink-0 px-1 text-sm font-semibold text-brand-700">Limpar</button>
                        @if ($ordering)
                            {{-- Carrinho sempre à mão, com a quantidade de itens. --}}
                            <a href="{{ $checkoutUrl }}" class="relative flex size-11 shrink-0 items-center justify-center rounded-2xl border border-stone-200 bg-white text-stone-700 shadow-xs transition active:scale-95"
                               :class="$store.cart.count > 0 && 'border-brand-200 bg-brand-50 text-brand-700'" :aria-label="'Carrinho, ' + $store.cart.count + ' itens'">
                                <x-icon name="bag" class="size-5" />
                                <span x-cloak x-show="$store.cart.count > 0" x-text="$store.cart.count"
                                      class="absolute -top-1.5 -right-1.5 flex min-w-5 items-center justify-center rounded-full bg-brand-600 px-1 text-[11px] font-bold text-white ring-2 ring-stone-50"></span>
                            </a>
                        @endif
                    </div>
                    <nav x-ref="chips" x-show="! term.trim()" class="flex gap-2 overflow-x-auto px-4 py-3 scrollbar-none" aria-label="Categorias">
                        @if ($featured->isNotEmpty())
                            <button type="button" data-chip="vitrine" @click="goTo('vitrine')" class="chip" :class="active === 'vitrine' && 'chip-active'">{{ $featuredTitle }}</button>
                        @endif
                        @if ($combos->isNotEmpty())
                            <button type="button" data-chip="combos" @click="goTo('combos')" class="chip" :class="active === 'combos' && 'chip-active'">Combos</button>
                        @endif
                        @foreach ($categories as $category)
                            <button type="button" data-chip="categoria-{{ $category->id }}" @click="goTo('categoria-{{ $category->id }}')" class="chip"
                                    :class="active === 'categoria-{{ $category->id }}' && 'chip-active'">{{ $category->name }}</button>
                        @endforeach
                    </nav>
                </div>
            @endif

            {{-- Busca no próprio cardápio: os resultados tomam o lugar das seções. --}}
            <div x-show="term.trim()" x-cloak class="px-4 pt-4">
                @include('public-menu.partials.search-results')
            </div>

            <main x-show="! term.trim()" class="space-y-8 px-4 pt-5">
                @if ($categories->isEmpty() && $combos->isEmpty())
                    <div class="card">
                        <x-ui.empty icon="utensils" title="Cardápio em preparação" text="O cardápio ainda está sendo montado. Volte em breve!" />
                    </div>
                @endif

                {{-- Vitrine: destaques escolhidos pelo restaurante (ou os mais pedidos), com foto grande --}}
                @if ($featured->isNotEmpty())
                    <section id="vitrine" data-menu-section>
                        <h2 class="flex items-center gap-2 text-lg font-bold tracking-tight">
                            <x-icon name="flame" class="size-5 text-brand-600" /> {{ $featuredTitle }}
                        </h2>
                        <div class="-mx-4 mt-3 flex snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto px-4 pb-2 scrollbar-none">
                            @foreach ($featured as $product)
                                <button type="button" @click="$dispatch('open-product', {{ $product->id }})"
                                        class="relative w-[78%] max-w-80 shrink-0 snap-start overflow-hidden rounded-3xl border border-stone-200/80 bg-white text-left shadow-(--shadow-card) transition active:scale-[0.98]">
                                    <div class="relative">
                                        @if ($product->images->first())
                                            <img src="{{ $productImage($product->images->first()) }}" alt="" loading="lazy" @class(['aspect-[16/10] w-full object-cover', 'grayscale' => ! $product->is_available])>
                                        @else
                                            <div class="flex h-24 w-full items-center justify-center bg-gradient-to-br from-brand-50 to-brand-100 text-brand-400">
                                                <x-icon name="utensils" class="size-8" />
                                            </div>
                                        @endif
                                        @if (in_array($product->id, $popular, true))
                                            <span class="absolute top-3 left-3 inline-flex items-center gap-1 rounded-full bg-white/95 px-2.5 py-1 text-xs font-bold text-brand-700 shadow-sm"><x-icon name="flame" class="size-3.5" /> Mais pedido</span>
                                        @endif
                                        <span x-cloak x-show="$store.cart.qty({{ $product->id }})" x-text="$store.cart.qty({{ $product->id }}) + ' no carrinho'"
                                              class="absolute top-3 right-3 rounded-full bg-brand-600 px-2.5 py-1 text-xs font-bold text-white shadow-sm"></span>
                                    </div>
                                    <div class="flex items-end gap-3 p-4">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate font-bold">{{ $product->name }}</p>
                                            @if ($product->description)<p class="mt-0.5 truncate text-sm text-stone-500">{{ $product->description }}</p>@endif
                                            <p class="mt-1.5 font-bold text-brand-700">{{ $product->price_cents->format() }}</p>
                                        </div>
                                        @if ($ordering && $product->is_available)
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-600 text-white shadow-md"><x-icon name="plus" class="size-5" stroke="2.4" /></span>
                                        @elseif (! $product->is_available)
                                            <span class="badge-gray shrink-0">Esgotado</span>
                                        @endif
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Combos --}}
                @if ($combos->isNotEmpty())
                    <section id="combos" data-menu-section class="scroll-mt-20">
                        <h2 class="text-lg font-bold tracking-tight">Combos</h2>
                        <div class="mt-3 space-y-3">
                            @foreach ($combos as $combo)
                                <article class="card flex gap-3 overflow-hidden p-3">
                                    @if ($combo->image_path)
                                        <img src="{{ $base }}/combos/{{ $combo->id }}/imagem" alt="" loading="lazy" class="size-24 shrink-0 rounded-xl object-cover">
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <h3 class="font-semibold">{{ $combo->name }}</h3>
                                            @unless ($combo->savings()->isZero())
                                                <span class="badge-green shrink-0">−{{ $combo->savings()->format() }}</span>
                                            @endunless
                                        </div>
                                        <p class="mt-0.5 text-xs text-stone-500">{{ $combo->items->map(fn ($i) => $i->quantity.'× '.$i->product?->name)->implode(' · ') }}</p>
                                        @if ($combo->description)
                                            <p class="mt-1 line-clamp-2 text-sm text-stone-500">{{ $combo->description }}</p>
                                        @endif
                                        <div class="mt-2 flex items-center justify-between gap-2">
                                            <p class="font-bold">{{ $combo->price_cents->format() }}</p>
                                            @if ($ordering)
                                                <button type="button" class="btn-primary btn-sm rounded-full"
                                                        @click="$store.cart.add({ type: 'combo', id: {{ $combo->id }}, name: @js($combo->name), unit: {{ $combo->price_cents->cents }}, addons: [], quantity: 1, notes: '' })">
                                                    <x-icon name="plus" class="size-4" /> Adicionar
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Categorias e produtos --}}
                @foreach ($categories as $category)
                    <section id="categoria-{{ $category->id }}" data-menu-section class="scroll-mt-20">
                        <h2 class="text-lg font-bold tracking-tight">{{ $category->name }}</h2>
                        @if ($category->description)
                            <p class="text-sm text-stone-500">{{ $category->description }}</p>
                        @endif

                        <div class="card mt-3 divide-y divide-stone-100 overflow-hidden">
                            @foreach ($category->products as $product)
                                <button type="button" @click="$dispatch('open-product', {{ $product->id }})"
                                        class="flex w-full gap-3 p-4 text-left transition hover:bg-stone-50 active:bg-stone-100">
                                    <div @class(['min-w-0 flex-1', 'opacity-60' => ! $product->is_available])>
                                        @if (in_array($product->id, $popular, true))
                                            <span class="mb-1 inline-flex items-center gap-1 text-xs font-bold text-brand-700"><x-icon name="flame" class="size-3.5" /> Mais pedido</span>
                                        @endif
                                        <h3 class="font-semibold text-stone-900">{{ $product->name }}</h3>
                                        @if ($product->description)
                                            <p class="mt-1 line-clamp-2 text-sm text-stone-500">{{ $product->description }}</p>
                                        @endif
                                        <p class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                                            <span class="font-bold text-stone-900">
                                                @if ($product->addonGroups->contains(fn ($g) => $g->isRequired()))<span class="font-normal text-stone-500">A partir de</span>@endif
                                                {{ $product->price_cents->format() }}
                                            </span>
                                            @unless ($product->is_available)
                                                <span class="badge-gray">Esgotado</span>
                                            @endunless
                                            <span x-cloak x-show="$store.cart.qty({{ $product->id }})" x-text="$store.cart.qty({{ $product->id }}) + ' no carrinho'" class="badge-brand"></span>
                                        </p>
                                    </div>
                                    <div class="relative shrink-0">
                                        @if ($product->images->first())
                                            <img src="{{ $productImage($product->images->first()) }}" alt="" loading="lazy" @class(['size-28 rounded-2xl object-cover', 'grayscale' => ! $product->is_available])>
                                        @endif
                                        @if ($ordering && $product->is_available)
                                            <span @class([
                                                'flex size-8 items-center justify-center rounded-full bg-white text-brand-600 shadow-md ring-1 ring-stone-200',
                                                'absolute -right-1 -bottom-1' => $product->images->isNotEmpty(),
                                                'mt-1' => $product->images->isEmpty(),
                                            ])>
                                                <x-icon name="plus" class="size-5" stroke="2.4" />
                                            </span>
                                        @endif
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <p class="pt-2 pb-4 text-center text-xs text-stone-400">Cardápio digital · {{ config('app.name') }}</p>
            </main>
        </div>

        {{-- ============ ABA BUSCAR ============ --}}
        <div x-show="tab === 'buscar'" x-cloak>
            <div class="sticky top-0 z-20 bg-stone-50/95 px-4 pt-6 pb-3 backdrop-blur-lg">
                <h1 class="text-2xl font-extrabold tracking-tight">Buscar</h1>
                <label class="relative mt-3 block">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-stone-400" />
                    <input x-ref="search" x-model.debounce.150ms="term" type="search" placeholder="Pizza, bebida, sobremesa..." autocomplete="off"
                           class="input h-12 rounded-2xl pl-12 text-base" aria-label="Buscar no cardápio">
                </label>
            </div>

            <div class="px-4 pt-2">
                {{-- Sem termo: atalhos para as categorias --}}
                <div x-show="! term.trim()">
                    <p class="stat-label mb-3">Categorias</p>
                    <div class="grid grid-cols-2 gap-3">
                        @if ($combos->isNotEmpty())
                            <button type="button" @click="goTo('combos')" class="card flex items-center gap-3 p-4 text-left font-semibold active:scale-[0.98]">
                                <span class="flex size-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="gift" class="size-5" /></span>
                                Combos
                            </button>
                        @endif
                        @foreach ($categories as $category)
                            <button type="button" @click="goTo('categoria-{{ $category->id }}')" class="card flex items-center gap-3 p-4 text-left font-semibold active:scale-[0.98]">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="utensils" class="size-5" /></span>
                                <span class="min-w-0">
                                    <span class="block truncate">{{ $category->name }}</span>
                                    <span class="block text-xs font-normal text-stone-500">{{ $category->products->count() }} {{ $category->products->count() === 1 ? 'item' : 'itens' }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Resultados --}}
                <div x-show="term.trim()">
                    @include('public-menu.partials.search-results')
                </div>
            </div>
        </div>

        {{-- ============ ABA PEDIDOS ============ --}}
        <div x-show="tab === 'pedidos'" x-cloak class="space-y-4 px-4 pt-6">
            @if ($table)
                @include('public-menu.partials.table-bill')
            @else
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight">Seus pedidos</h1>
                <p class="mt-1 text-sm text-stone-500">Pedidos feitos neste aparelho.</p>
            </div>

            @if ($ordering)
                <a href="{{ $checkoutUrl }}" x-show="$store.cart.count > 0" class="card flex items-center gap-3 border-brand-200 bg-brand-50 p-4">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-brand-600 text-white"><x-icon name="bag" class="size-5" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold">Sacola em aberto</span>
                        <span class="block text-sm text-stone-600"><span x-text="$store.cart.count"></span> <span x-text="$store.cart.count === 1 ? 'item' : 'itens'"></span> · <span x-text="money($store.cart.subtotal)"></span></span>
                    </span>
                    <x-icon name="chevron-right" class="size-5 text-brand-600" />
                </a>
            @endif

            <template x-if="$store.orders.list.length === 0">
                <div class="card">
                    <x-ui.empty icon="bag" title="Nenhum pedido por aqui ainda" text="Quando você fizer um pedido, ele aparece aqui para acompanhar em tempo real.">
                        <a href="#cardapio" class="btn-primary">Ver cardápio</a>
                    </x-ui.empty>
                </div>
            </template>

            <div class="space-y-3">
                <template x-for="order in $store.orders.list" :key="order.url">
                    <a :href="order.url" class="card flex items-center gap-3 p-4 active:scale-[0.99]">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl"
                              :class="order.status === 'cancelled' ? 'bg-red-50 text-red-600' : (order.final ? 'bg-emerald-50 text-emerald-600' : 'bg-brand-50 text-brand-600')">
                            <x-icon name="bag" class="size-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="font-bold" x-text="'#' + order.number"></span>
                                <span x-show="! order.final" class="badge-brand">Em andamento</span>
                            </span>
                            <span class="block truncate text-sm text-stone-700" x-text="order.label"></span>
                            <span class="block text-xs text-stone-500" x-text="[order.placed_at, order.total].filter(Boolean).join(' · ')"></span>
                        </span>
                        @if ($ordering)
                            <button type="button" x-show="order.final" class="btn-secondary btn-sm shrink-0 rounded-full"
                                    @click.prevent.stop="reorder(order.url, @js($base.'/pedido'))">
                                <x-icon name="refresh" class="size-4" /> Pedir de novo
                            </button>
                        @endif
                        <x-icon name="chevron-right" class="size-5 text-stone-400" x-show="! order.final || ! ordering" />
                    </a>
                </template>
            </div>
            @endif
        </div>

        {{-- ============ ABA LOJA ============ --}}
        <div x-show="tab === 'loja'" x-cloak class="space-y-4 px-4 pt-6">
            <div class="flex items-center gap-3">
                @if ($establishment->logo_path)
                    <img src="{{ $base }}/logo" alt="" class="size-14 rounded-2xl border border-stone-200 bg-white object-contain">
                @endif
                <div class="min-w-0">
                    <h1 class="truncate text-2xl font-extrabold tracking-tight">{{ $name }}</h1>
                    <p class="text-sm {{ $isOpen ? 'text-emerald-700' : 'text-stone-500' }}">{{ $isOpen ? 'Aberto agora' : 'Fechado agora' }} · {{ $todayLabel }}</p>
                </div>
            </div>

            @if ($establishment->address() || $establishment->phone)
                <section class="card divide-y divide-stone-100">
                    @if ($establishment->address())
                        <div class="flex items-start gap-3 p-4">
                            <x-icon name="pin" class="mt-0.5 size-5 text-brand-600" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm">{{ $establishment->address() }}</p>
                                <a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="btn-link mt-1 inline-block">Como chegar</a>
                            </div>
                        </div>
                    @endif
                    @if ($establishment->phone)
                        <div class="flex items-center gap-3 p-4">
                            <x-icon name="phone" class="size-5 text-brand-600" />
                            <p class="flex-1 text-sm">{{ $establishment->phone }}</p>
                            <a href="tel:{{ $digits }}" class="btn-secondary btn-sm">Ligar</a>
                            @if ($whatsapp)
                                <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn-success btn-sm">WhatsApp</a>
                            @endif
                        </div>
                    @endif
                </section>
            @endif

            <section class="card">
                <div class="card-header"><h2 class="section-title flex items-center gap-2"><x-icon name="clock" class="size-5 text-brand-600" /> Horários</h2></div>
                <dl class="divide-y divide-stone-100 text-sm">
                    @foreach ($week as $day)
                        <div @class(['flex justify-between px-5 py-2.5', 'bg-brand-50/60 font-semibold text-brand-800' => $day['weekday'] === $today])>
                            <dt>{{ $day['label'] }} @if ($day['weekday'] === $today)<span class="badge-brand ml-1">Hoje</span>@endif</dt>
                            <dd @class(['text-stone-400' => $day['hours'] === 'Fechado'])>{{ $day['hours'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="section-title flex items-center gap-2"><x-icon name="truck" class="size-5 text-brand-600" /> Entrega e retirada</h2></div>
                <ul class="space-y-2 p-5 text-sm">
                    @if ($acceptsDelivery)
                        @if ($zones->isNotEmpty())
                            <li class="text-stone-500">Bairros atendidos</li>
                            @foreach ($zones as $zone)
                                <li class="flex justify-between pl-3">
                                    <span>{{ $zone->name }}@if ($zone->eta_minutes)<span class="text-xs text-stone-400"> · ~{{ $zone->eta_minutes }} min</span>@endif</span>
                                    <span class="font-medium">{{ $zone->fee_cents->isZero() ? 'Grátis' : $zone->fee_cents->format() }}</span>
                                </li>
                            @endforeach
                        @else
                            <li class="flex justify-between"><span class="text-stone-500">Entrega</span><span class="font-medium">{{ $deliveryFee->isZero() ? 'Grátis' : 'Taxa de '.$deliveryFee->format() }}</span></li>
                        @endif
                    @endif
                    @if ($acceptsPickup)
                        <li class="flex justify-between"><span class="text-stone-500">Retirada</span><span class="font-medium">Retirada no local</span></li>
                    @endif
                    @unless ($minOrder->isZero())
                        <li class="flex justify-between"><span class="text-stone-500">Pedido mínimo</span><span class="font-medium">{{ $minOrder->format() }}</span></li>
                    @endunless
                </ul>
            </section>

            @if ($paymentMethods->isNotEmpty())
                <section class="card">
                    <div class="card-header"><h2 class="section-title flex items-center gap-2"><x-icon name="wallet" class="size-5 text-brand-600" /> Pagamento</h2></div>
                    <div class="p-5">
                        <p class="text-xs text-stone-500">Pague na entrega ou na retirada:</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($paymentMethods as $method)
                                <span class="rounded-full border border-stone-200 bg-stone-50 px-3 py-1 text-sm font-medium">{{ $method->name }}</span>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            @if ($establishment->legal_name || $establishment->document)
                <p class="text-center text-xs text-stone-400">{{ collect([$establishment->legal_name, $establishment->document])->filter()->implode(' · ') }}</p>
            @endif
        </div>

        {{-- Barra do carrinho, acima da navegação --}}
        @if ($ordering)
            <div x-cloak x-show="$store.cart.count > 0" x-transition.opacity
                 class="fixed inset-x-0 z-30 mx-auto max-w-xl px-3" style="bottom: calc(max(env(safe-area-inset-bottom), 0.5rem) + 4.25rem)">
                <a href="{{ $checkoutUrl }}" class="flex items-center gap-3 rounded-2xl bg-brand-600 px-4 py-3 text-white shadow-float transition duration-200 active:scale-[0.98]"
                   :class="$store.cart.pulse && 'scale-[1.03] bg-brand-700'">
                    <span class="relative">
                        <x-icon name="bag" class="size-6" />
                        <span class="absolute -top-1.5 -right-2 flex min-w-5 items-center justify-center rounded-full bg-white px-1 text-[11px] font-bold text-brand-700" x-text="$store.cart.count"></span>
                    </span>
                    <span class="flex-1 leading-tight">
                        <span class="block font-semibold">Ver carrinho</span>
                        <span class="block text-xs text-white/80" x-text="$store.cart.count === 1 ? '1 item' : $store.cart.count + ' itens'"></span>
                    </span>
                    <span class="font-bold" x-text="money($store.cart.subtotal)"></span>
                    <x-icon name="chevron-right" class="size-5" />
                </a>
            </div>
        @endif

        {{-- Aviso rápido ("adicionado à sacola") --}}
        <div x-cloak x-show="$store.cart.toast" x-transition
             class="fixed inset-x-0 top-4 z-[60] mx-auto flex w-fit max-w-[90%] items-center gap-2 rounded-full bg-stone-900 px-4 py-2.5 text-sm font-medium text-white shadow-float">
            <x-icon name="check" class="size-4 text-emerald-400" />
            <span x-text="$store.cart.toast"></span>
        </div>

        {{-- Folha de detalhe do produto --}}
        <div x-data="picker()" @open-product.window="show($event.detail)" @popstate.window="open && hide()">
            <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-40 bg-stone-950/60" @click="close()"></div>
            <section x-cloak x-show="open" role="dialog" aria-modal="true" :aria-label="product?.name"
                     x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                     x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                     class="fixed inset-x-0 bottom-0 z-50 mx-auto flex max-h-[92vh] max-w-xl flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl">
                <template x-if="product">
                    <div class="flex min-h-0 flex-1 flex-col">
                        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                            <div class="relative">
                                <template x-if="product.images.length">
                                    <div>
                                        <div class="flex snap-x snap-mandatory overflow-x-auto scrollbar-none" @scroll.debounce.50ms="photo = Math.round($el.scrollLeft / $el.clientWidth)">
                                            <template x-for="src in product.images" :key="src">
                                                <img :src="src" :alt="product.name" class="aspect-[4/3] w-full shrink-0 snap-center object-cover">
                                            </template>
                                        </div>
                                        <div class="absolute inset-x-0 bottom-3 flex justify-center gap-1.5" x-show="product.images.length > 1">
                                            <template x-for="(src, i) in product.images" :key="'dot' + i">
                                                <span class="h-1.5 rounded-full bg-white transition-all" :class="photo === i ? 'w-5' : 'w-1.5 opacity-60'"></span>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                                <div x-show="! product.images.length" class="flex justify-center pt-3"><span class="h-1.5 w-10 rounded-full bg-stone-300"></span></div>
                                <button type="button" @click="close()" class="absolute top-3 right-3 flex size-9 items-center justify-center rounded-full bg-white/90 text-stone-700 shadow-md backdrop-blur" aria-label="Fechar">
                                    <x-icon name="x" class="size-5" />
                                </button>
                            </div>

                            <div class="space-y-5 p-5">
                                <div class="pr-8">
                                    <p class="text-xs font-semibold tracking-wide text-brand-600 uppercase" x-text="product.category"></p>
                                    <h3 class="mt-1 text-xl leading-tight font-extrabold tracking-tight" x-text="product.name"></h3>
                                    <p class="mt-2 text-sm whitespace-pre-line text-stone-600" x-show="product.description" x-text="product.description"></p>
                                    <p class="mt-3 text-lg font-bold" x-text="money(product.price)"></p>
                                    <p x-show="! product.available" class="alert-warning mt-3">Esgotado no momento.</p>
                                    <p x-show="product.available && ! ordering" class="mt-3 text-sm text-stone-500">Pedidos online indisponíveis agora.</p>
                                </div>

                                <template x-for="group in product.groups" :key="group.id">
                                    <div class="-mx-5">
                                        <div class="flex items-center justify-between gap-2 bg-stone-100 px-5 py-3">
                                            <div>
                                                <p class="font-semibold" x-text="group.name"></p>
                                                <p class="text-xs text-stone-500" x-text="group.rule"></p>
                                            </div>
                                            <template x-if="group.min > 0">
                                                <span :class="(selected[group.id] || []).length >= group.min ? 'badge-green' : 'badge bg-stone-800 text-white'"
                                                      x-text="(selected[group.id] || []).length >= group.min ? 'OK' : 'Obrigatório'"></span>
                                            </template>
                                        </div>
                                        <ul class="divide-y divide-stone-100">
                                            <template x-for="addon in group.addons" :key="addon.id">
                                                <li>
                                                    <button type="button" class="flex w-full items-center justify-between gap-3 px-5 py-3.5 text-left text-sm"
                                                            @click="toggle(group.id, addon.id)" :disabled="! canOrder"
                                                            :class="! has(group.id, addon.id) && full(group.id) && 'opacity-40'">
                                                        <span class="min-w-0">
                                                            <span class="block font-medium" x-text="addon.name"></span>
                                                            <span class="block text-stone-500" x-text="addon.price ? '+ ' + money(addon.price) : 'sem custo'"></span>
                                                        </span>
                                                        <span x-show="canOrder" class="flex size-6 shrink-0 items-center justify-center border-2 transition"
                                                              :class="[group.max === 1 ? 'rounded-full' : 'rounded-md', has(group.id, addon.id) ? 'border-brand-600 bg-brand-600 text-white' : 'border-stone-300']">
                                                            <x-icon name="check" class="size-4" stroke="3" x-show="has(group.id, addon.id)" />
                                                        </span>
                                                    </button>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </template>

                                <div x-show="canOrder">
                                    <label class="label" for="product-notes">Alguma observação?</label>
                                    <textarea id="product-notes" x-model="notes" maxlength="200" rows="2" class="input" placeholder="Ex.: sem cebola, ponto da carne..."></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-stone-100 bg-white p-4" style="padding-bottom: max(env(safe-area-inset-bottom), 1rem)">
                            <template x-if="canOrder">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-12 items-center rounded-2xl border border-stone-200">
                                        <button type="button" class="flex size-12 items-center justify-center text-brand-600 disabled:text-stone-300" @click="quantity = Math.max(1, quantity - 1)" :disabled="quantity === 1" aria-label="Diminuir">
                                            <x-icon name="minus" class="size-5" stroke="2.4" />
                                        </button>
                                        <span class="w-6 text-center font-bold" x-text="quantity"></span>
                                        <button type="button" class="flex size-12 items-center justify-center text-brand-600" @click="quantity = Math.min(99, quantity + 1)" aria-label="Aumentar">
                                            <x-icon name="plus" class="size-5" stroke="2.4" />
                                        </button>
                                    </div>
                                    <button type="button" class="btn-primary btn-lg min-w-0 flex-1 justify-between px-5" @click="add()" :disabled="missing.length > 0">
                                        <span class="truncate" x-text="missing.length ? 'Selecione: ' + missing[0].name : 'Adicionar'"></span>
                                        <span x-show="! missing.length" x-text="money(total)"></span>
                                    </button>
                                </div>
                            </template>
                            <template x-if="! canOrder">
                                <button type="button" class="btn-secondary btn-lg w-full" @click="close()">Voltar ao cardápio</button>
                            </template>
                        </div>
                    </div>
                </template>
            </section>
        </div>

        @include('public-menu.partials.bottom-nav', ['menuUrl' => $table ? $tableUrl : $base, 'current' => null, 'tableMode' => (bool) $table])
    </div>

    <script>
        window.menuCatalog = @js($catalog);
        window.menuCombos = @js($combosCatalog);
        window.menuOrdering = @js($ordering);
        // Carrinho da mesa separado do carrinho de entrega/retirada.
        window.cartScope = @js($table ? 'mesa-'.$table->id : null);

        document.addEventListener('alpine:init', () => {
            // Casca do app: abas pelo #hash (o "voltar" do celular troca de aba), categorias que
            // acompanham a rolagem e busca local, sem ida ao servidor.
            Alpine.data('menuApp', () => ({
                tabs: ['cardapio', 'buscar', 'pedidos', 'loja'],
                tab: 'cardapio',
                term: '',
                active: null,
                scrolls: {},
                ordering: window.menuOrdering,
                init() {
                    // A rolagem de cada aba é controlada aqui (abrir/fechar a folha não pode pular a tela).
                    history.scrollRestoration = 'manual';
                    this.route();
                    window.addEventListener('hashchange', () => this.route());
                    this.spy();
                    this.$store.orders.refresh();
                    // Começou a buscar pelo topo do cardápio: leva até o início dos resultados.
                    this.$watch('term', (value, old) => {
                        if (this.tab !== 'cardapio' || ! value.trim() || old.trim()) return;
                        const top = this.$refs.menuTop?.offsetTop ?? 0;
                        if (window.scrollY > top) window.scrollTo({ top });
                    });
                },
                // Altura da barra fixa (busca + categorias), para a seção não ficar escondida atrás dela.
                stickyOffset() {
                    return (this.$refs.sticky?.offsetHeight ?? 56) + 8;
                },
                route() {
                    const hash = location.hash.slice(1);
                    const next = this.tabs.includes(hash) ? hash : 'cardapio';
                    if (next === this.tab) return;
                    this.switchTo(next);
                },
                switchTo(next, after = null) {
                    this.scrolls[this.tab] = window.scrollY;
                    this.tab = next;
                    this.$nextTick(() => {
                        if (after) after(); else window.scrollTo(0, this.scrolls[next] || 0);
                        if (next === 'buscar') this.$refs.search?.focus();
                        if (next === 'pedidos') this.$store.orders.refresh();
                    });
                },
                // Toque na aba atual: volta ao topo, como nos apps.
                reselect(id, event) {
                    if (this.tab !== id) return;
                    event.preventDefault();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
                goTo(id) {
                    const scroll = () => {
                        const el = document.getElementById(id);
                        if (el) window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - this.stickyOffset(), behavior: 'smooth' });
                    };
                    this.term = '';
                    if (this.tab === 'cardapio') return this.$nextTick(scroll);
                    history.pushState(null, '', '#cardapio');
                    this.switchTo('cardapio', scroll);
                },
                spy() {
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach((entry) => { if (entry.isIntersecting) this.active = entry.target.id; });
                    }, { rootMargin: '-120px 0px -65% 0px' });
                    document.querySelectorAll('[data-menu-section][id]').forEach((section) => observer.observe(section));
                    this.$watch('active', (id) => {
                        const bar = this.$refs.chips;
                        const chip = bar?.querySelector(`[data-chip="${id}"]`);
                        if (chip) bar.scrollTo({ left: chip.offsetLeft - bar.clientWidth / 2 + chip.clientWidth / 2, behavior: 'smooth' });
                    });
                },
                get results() {
                    const term = normalize(this.term.trim());
                    if (! term) return [];
                    return Object.values(window.menuCatalog).filter((p) => normalize(`${p.name} ${p.description || ''} ${p.category}`).includes(term));
                },
                get comboResults() {
                    const term = normalize(this.term.trim());
                    if (! term) return [];
                    return window.menuCombos.filter((c) => normalize(`${c.name} ${c.items} ${c.description || ''}`).includes(term));
                },
                share() {
                    const data = { title: @js($name), url: @js($base) };
                    if (navigator.share) return navigator.share(data).catch(() => {});
                    navigator.clipboard?.writeText(data.url).then(() => this.$store.cart.notify('Link do cardápio copiado'));
                },
            }));
        });
    </script>

    @include('public-menu.partials.cart-script')
</x-layouts.public>
